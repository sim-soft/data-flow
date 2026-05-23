<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

use ArrayIterator;
use Generator;
use Iterator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Simsoft\DataFlow\Enums\CircuitState;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Metrics\NullMetricsExporter;
use Throwable;

/**
 * StageRunner
 *
 * Wraps a stage's iterator output with error handling, logging, and metrics.
 * Applies the configured error strategy per-row and records failures
 * to the dead-letter collection. Integrates circuit breaker and retry
 * with exponential backoff + jitter.
 */
final class StageRunner
{
    /** @var array<string, CircuitBreaker> Circuit breakers keyed by stage name. */
    private array $circuitBreakers = [];

    /**
     * Get the final circuit states for all stages that have circuit breakers configured.
     *
     * @return array<string, CircuitState>
     */
    public function getCircuitStates(): array
    {
        $states = [];
        foreach ($this->circuitBreakers as $stageName => $breaker) {
            $states[$stageName] = $breaker->getState();
        }
        return $states;
    }

    /**
     * Wrap a stage's iterator output with error handling and metrics.
     *
     * Iterates through the input, invoking the stage for each row individually.
     * Applies the configured error strategy when a row causes an exception.
     * Integrates circuit breaker logic: skips rows when circuit is Open,
     * records successes/failures to the breaker.
     * Logs stage boundary messages and row-level failures at appropriate levels.
     *
     * ⚠️ Caveat: Stateful transformers (such as {@see \Simsoft\DataFlow\Transformers\Chunk})
     * are invoked per-row when the error strategy is non-Throw. Each row is
     * wrapped in a single-row {@see \ArrayIterator}, which breaks transformers
     * that buffer state across rows. Use {@see \Simsoft\DataFlow\Enums\ErrorStrategy::Throw}
     * for stateful transformers, or split them into separate pipelines.
     *
     * @param Processor $stage The stage processor to run.
     * @param Iterator|null $input The input iterator for the stage.
     * @param ErrorStrategy $strategy The error handling strategy.
     * @param RetryConfig|null $retryConfig Retry configuration (required when strategy is Retry).
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     *
     * @return Generator<int, mixed, mixed, void>
     *
     * @throws Throwable When the throw strategy is active and a row fails.
     */
    public function run(
        Processor            $stage,
        ?Iterator            $input,
        ErrorStrategy        $strategy,
        ?RetryConfig         $retryConfig,
        LoggerInterface      $logger,
        DeadLetterCollection $deadLetters,
        ?callable            $onError,
        MetricsExporter      $metricsExporter = new NullMetricsExporter(),
    ): Generator
    {
        $stageName = $stage->getName();

        // Initialize circuit breaker for this stage if configured
        $circuitBreaker = $this->getOrCreateCircuitBreaker($stage);

        $logger->debug("Stage '{$stageName}' started");

        $rowCount = 0;
        $rowIndex = 0;

        if ($input === null) {
            // Extractor stage: invoke with null, iterate output
            yield from $this->iterateExtractorOutput(
                $stage,
                $strategy,
                $retryConfig,
                $logger,
                $deadLetters,
                $onError,
                $stageName,
                $rowCount,
                $rowIndex,
                $circuitBreaker,
                $metricsExporter,
            );

            $logger->info("Stage '{$stageName}' completed: {$rowCount} rows processed");
            return;
        }

        // Transformer/Loader stage: process each input row individually
        yield from $this->iterateTransformerOutput(
            $stage,
            $input,
            $strategy,
            $retryConfig,
            $logger,
            $deadLetters,
            $onError,
            $stageName,
            $rowCount,
            $rowIndex,
            $circuitBreaker,
            $metricsExporter,
        );

        $logger->info("Stage '{$stageName}' completed: {$rowCount} rows processed");
    }

    /**
     * Iterate the output of a transformer/loader stage with error handling.
     *
     * @param Processor $stage The stage processor.
     * @param Iterator $input The input iterator.
     * @param ErrorStrategy $strategy The error handling strategy.
     * @param RetryConfig|null $retryConfig Retry configuration.
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     * @param string $stageName The stage name.
     * @param int                  &$rowCount Row count reference.
     * @param int                  &$rowIndex Row index reference.
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker for this stage.
     * @param MetricsExporter $metricsExporter Metrics exporter.
     *
     * @return Generator<int, mixed, mixed, void>
     *
     * @throws Throwable When the throw strategy is active and a row fails.
     */
    private function iterateTransformerOutput(
        Processor            $stage,
        Iterator             $input,
        ErrorStrategy        $strategy,
        ?RetryConfig         $retryConfig,
        LoggerInterface      $logger,
        DeadLetterCollection $deadLetters,
        ?callable            $onError,
        string               $stageName,
        int                  &$rowCount,
        int                  &$rowIndex,
        ?CircuitBreaker      $circuitBreaker,
        MetricsExporter      $metricsExporter,
    ): Generator
    {
        foreach ($input as $row) {
            $rowIndex++;

            // Circuit breaker check: skip row if circuit is Open
            if ($circuitBreaker !== null && !$circuitBreaker->isCallAllowed()) {
                $this->recordCircuitOpenSkip($deadLetters, $row, $stageName, $rowIndex);
                $metricsExporter->recordRowFailed($stageName, new RuntimeException('circuit-open'));
                $logger->debug("Row {$rowIndex} skipped in stage '{$stageName}': circuit-open");
                continue;
            }

            try {
                $singleRowIterator = new ArrayIterator([$row]);
                $output = $stage($singleRowIterator);

                foreach ($output as $outputRow) {
                    $rowCount++;
                    $circuitBreaker?->recordSuccess();
                    yield $outputRow;
                }
            } catch (Throwable $exception) {
                $this->logError($logger, $stageName, $exception, $rowIndex, $row);

                if ($strategy === ErrorStrategy::Throw) {
                    $circuitBreaker?->recordFailure();
                    throw $exception;
                }

                if ($strategy === ErrorStrategy::Retry) {
                    $resolved = $this->handleRetry(
                        $stage,
                        $row,
                        $retryConfig,
                        $logger,
                        $deadLetters,
                        $onError,
                        $exception,
                        $rowIndex,
                        $stageName,
                    );

                    if ($resolved === null) {
                        $circuitBreaker?->recordFailure();
                        $metricsExporter->recordRowFailed($stageName, $exception);
                        continue;
                    }

                    $rowCount++;
                    $circuitBreaker?->recordSuccess();
                    yield $resolved;
                    continue;
                }

                // Skip or LogAndContinue
                $circuitBreaker?->recordFailure();
                $this->logWarning($logger, $rowIndex, $stageName, $exception);
                $this->recordFailure($deadLetters, $row, $stageName, $rowIndex, $exception);
                $this->invokeOnError($onError, $exception, $row, $stageName);
                $metricsExporter->recordRowFailed($stageName, $exception);

                if ($strategy === ErrorStrategy::LogAndContinue) {
                    $rowCount++;
                    yield $row;
                }
            }
        }
    }

    /**
     * Iterate the output of an extractor stage (null input) with error handling.
     *
     * @param Processor $stage The stage processor.
     * @param ErrorStrategy $strategy The error handling strategy.
     * @param RetryConfig|null $retryConfig Retry configuration.
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     * @param string $stageName The stage name.
     * @param int                  &$rowCount Row count reference.
     * @param int                  &$rowIndex Row index reference.
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker for this stage.
     *
     * @return Generator<int, mixed, mixed, void>
     *
     * @throws Throwable When the throw strategy is active and a row fails.
     */
    private function iterateExtractorOutput(
        Processor            $stage,
        ErrorStrategy        $strategy,
        ?RetryConfig         $retryConfig,
        LoggerInterface      $logger,
        DeadLetterCollection $deadLetters,
        ?callable            $onError,
        string               $stageName,
        int                  &$rowCount,
        int                  &$rowIndex,
        ?CircuitBreaker      $circuitBreaker,
        MetricsExporter      $metricsExporter,
    ): Generator
    {
        $output = $stage(null);

        while (true) {
            try {
                if (!$output->valid()) {
                    break;
                }

                $row = $output->current();
            } catch (Throwable $exception) {
                $rowIndex++;

                $this->logError($logger, $stageName, $exception, $rowIndex);

                if ($strategy === ErrorStrategy::Throw) {
                    $circuitBreaker?->recordFailure();
                    throw $exception;
                }

                if ($strategy === ErrorStrategy::Retry) {
                    $this->handleExtractorRetry(
                        $logger,
                        $deadLetters,
                        $onError,
                        $retryConfig,
                        $exception,
                        $rowIndex,
                        $stageName,
                        $circuitBreaker,
                    );

                    $metricsExporter->recordRowFailed($stageName, $exception);

                    try {
                        $output->next();
                    } catch (Throwable) {
                        // Will be caught in next loop iteration
                    }
                    continue;
                }

                $circuitBreaker?->recordFailure();
                $this->logWarning($logger, $rowIndex, $stageName, $exception);
                $this->recordFailure($deadLetters, null, $stageName, $rowIndex, $exception);
                $this->invokeOnError($onError, $exception, null, $stageName);
                $metricsExporter->recordRowFailed($stageName, $exception);

                try {
                    $output->next();
                } catch (Throwable) {
                    // Will be caught in next loop iteration
                }
                continue;
            }

            $rowIndex++;

            // Circuit breaker check for extractor rows
            if ($circuitBreaker !== null && !$circuitBreaker->isCallAllowed()) {
                $this->recordCircuitOpenSkip($deadLetters, $row, $stageName, $rowIndex);
                $metricsExporter->recordRowFailed($stageName, new RuntimeException('circuit-open'));
                $logger->debug("Row {$rowIndex} skipped in stage '{$stageName}': circuit-open");

                try {
                    $output->next();
                } catch (Throwable) {
                    // Will be caught in next loop iteration
                }
                continue;
            }

            $rowCount++;
            $circuitBreaker?->recordSuccess();
            yield $row;

            try {
                $output->next();
            } catch (Throwable) {
                // Will be caught in next loop iteration via valid()/current()
            }
        }
    }

    /**
     * Handle the retry strategy for a failing row.
     *
     * Attempts to re-invoke the stage up to maxAttempts times with exponential
     * backoff + jitter delay computed via RetryConfig. If all attempts fail,
     * records the failure and adds to dead-letter collection.
     *
     * @param Processor $stage The stage processor.
     * @param mixed $row The failing row data.
     * @param RetryConfig|null $retryConfig Retry configuration.
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     * @param Throwable $exception The initial exception.
     * @param int $rowIndex The current row index.
     * @param string $stageName The stage name.
     *
     * @return mixed The successfully processed row, or null if all attempts failed.
     */
    private function handleRetry(
        Processor            $stage,
        mixed                $row,
        ?RetryConfig         $retryConfig,
        LoggerInterface      $logger,
        DeadLetterCollection $deadLetters,
        ?callable            $onError,
        Throwable            $exception,
        int                  $rowIndex,
        string               $stageName,
    ): mixed
    {
        $maxAttempts = $retryConfig !== null ? $retryConfig->maxAttempts : 3;
        $lastException = $exception;

        // First attempt already failed, so we start from attempt 2
        for ($attempt = 2; $attempt <= $maxAttempts; $attempt++) {
            // Compute delay with exponential backoff + jitter via RetryConfig
            $delayMs = $retryConfig !== null
                ? $retryConfig->applyJitter($retryConfig->computeDelay($attempt))
                : 100;

            usleep($delayMs * 1000);

            try {
                $singleRowIterator = new ArrayIterator([$row]);
                $retryOutput = $stage($singleRowIterator);

                if ($retryOutput->valid()) {
                    return $retryOutput->current();
                }

                break;
            } catch (Throwable $retryException) {
                $lastException = $retryException;
            }
        }

        // All attempts exhausted - add to dead-letter
        $this->logWarning($logger, $rowIndex, $stageName, $lastException);
        $this->recordFailure($deadLetters, $row, $stageName, $rowIndex, $lastException);
        $this->invokeOnError($onError, $lastException, $row, $stageName);

        return null;
    }

    /**
     * Handle retry for extractor stages where row data is unavailable.
     *
     * Applies exponential backoff + jitter delays between retry attempts.
     * Records the failure after all attempts are exhausted.
     *
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     * @param RetryConfig|null $retryConfig Retry configuration.
     * @param Throwable $exception The initial exception.
     * @param int $rowIndex The current row index.
     * @param string $stageName The stage name.
     * @param CircuitBreaker|null $circuitBreaker Circuit breaker for this stage.
     *
     * @return void
     */
    private function handleExtractorRetry(
        LoggerInterface      $logger,
        DeadLetterCollection $deadLetters,
        ?callable            $onError,
        ?RetryConfig         $retryConfig,
        Throwable            $exception,
        int                  $rowIndex,
        string               $stageName,
        ?CircuitBreaker      $circuitBreaker,
    ): void
    {
        $maxAttempts = $retryConfig !== null ? $retryConfig->maxAttempts : 3;

        // Apply backoff delays for remaining attempts
        for ($attempt = 2; $attempt <= $maxAttempts; $attempt++) {
            $delayMs = $retryConfig !== null
                ? $retryConfig->applyJitter($retryConfig->computeDelay($attempt))
                : 100;

            usleep($delayMs * 1000);
        }

        $circuitBreaker?->recordFailure();
        $this->logWarning($logger, $rowIndex, $stageName, $exception);
        $this->recordFailure($deadLetters, null, $stageName, $rowIndex, $exception);
        $this->invokeOnError($onError, $exception, null, $stageName);
    }

    /**
     * Log an error-level message for a stage exception.
     *
     * Includes row data in debug-level log context only.
     *
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param string $stageName The stage name.
     * @param Throwable $exception The caught exception.
     * @param int $rowIndex The row index.
     * @param mixed $rowData The row data for debug context.
     *
     * @return void
     */
    private function logError(
        LoggerInterface $logger,
        string          $stageName,
        Throwable       $exception,
        int             $rowIndex,
        mixed           $rowData = null,
    ): void
    {
        $logger->error(
            "Stage '{$stageName}' exception: {$exception->getMessage()} at row {$rowIndex}",
            [
                'stage' => $stageName,
                'rowIndex' => $rowIndex,
                'exception' => $exception,
            ],
        );

        $logger->debug(
            "Stage '{$stageName}' exception row data",
            ['row' => $rowData, 'rowIndex' => $rowIndex],
        );
    }

    /**
     * Log a warning-level message for a row failure.
     *
     * Contains row index, stage name, and exception message without full row data.
     *
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param int $rowIndex The row index.
     * @param string $stageName The stage name.
     * @param Throwable $exception The caught exception.
     *
     * @return void
     */
    private function logWarning(
        LoggerInterface $logger,
        int             $rowIndex,
        string          $stageName,
        Throwable       $exception,
    ): void
    {
        $logger->warning(
            "Row {$rowIndex} failed in stage '{$stageName}': {$exception->getMessage()}",
            [
                'rowIndex' => $rowIndex,
                'stage' => $stageName,
                'message' => $exception->getMessage(),
            ],
        );
    }

    /**
     * Record a failure in the dead-letter collection.
     *
     * @param DeadLetterCollection $deadLetters The dead-letter collection.
     * @param mixed $rowData The row data that failed.
     * @param string $stageName The stage name.
     * @param int $rowIndex The row index.
     * @param Throwable $exception The caught exception.
     *
     * @return void
     */
    private function recordFailure(
        DeadLetterCollection $deadLetters,
        mixed                $rowData,
        string               $stageName,
        int                  $rowIndex,
        Throwable            $exception,
    ): void
    {
        $deadLetters->add(new DeadLetterEntry(
            row: $rowData,
            stageName: $stageName,
            rowIndex: $rowIndex,
            exception: $exception,
        ));
    }

    /**
     * Invoke the onError callback if registered.
     *
     * @param callable|null $onError The error callback.
     * @param Throwable $exception The caught exception.
     * @param mixed $rowData The row data that failed.
     * @param string $stageName The stage name.
     *
     * @return void
     */
    private function invokeOnError(
        ?callable $onError,
        Throwable $exception,
        mixed     $rowData,
        string    $stageName,
    ): void
    {
        if ($onError === null) {
            return;
        }

        ($onError)($exception, $rowData, $stageName);
    }

    /**
     * Get or create a circuit breaker for the given stage.
     *
     * Returns null if the stage has no circuit breaker configured.
     *
     * @param Processor $stage The stage processor.
     *
     * @return CircuitBreaker|null
     */
    private function getOrCreateCircuitBreaker(Processor $stage): ?CircuitBreaker
    {
        $config = $stage->getCircuitBreakerConfig();

        if ($config === null) {
            return null;
        }

        $stageName = $stage->getName();

        if (!isset($this->circuitBreakers[$stageName])) {
            $this->circuitBreakers[$stageName] = new CircuitBreaker($config);
        }

        return $this->circuitBreakers[$stageName];
    }

    /**
     * Record a row skipped due to an open circuit breaker in the dead-letter collection.
     *
     * @param DeadLetterCollection $deadLetters The dead-letter collection.
     * @param mixed $row The row data that was skipped.
     * @param string $stageName The stage name.
     * @param int $rowIndex The row index.
     *
     * @return void
     */
    private function recordCircuitOpenSkip(
        DeadLetterCollection $deadLetters,
        mixed                $row,
        string               $stageName,
        int                  $rowIndex,
    ): void
    {
        $deadLetters->add(new DeadLetterEntry(
            row: $row,
            stageName: $stageName,
            rowIndex: $rowIndex,
            exception: new RuntimeException('circuit-open'),
        ));
    }
}
