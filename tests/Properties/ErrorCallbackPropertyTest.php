<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;
use Throwable;

/**
 * A concrete Processor test double that throws on specific rows.
 */
class ThrowingProcessor extends Processor
{
    /** @var array Rows that should trigger an exception */
    private array $failingRows;

    /** @var string The stage name */
    private string $stageName;

    /**
     * @param array $failingRows Rows that should cause an exception when processed.
     * @param string $stageName The name to assign to this processor.
     */
    public function __construct(array $failingRows, string $stageName = 'test-stage')
    {
        $this->failingRows = $failingRows;
        $this->stageName = $stageName;
        $this->withName($stageName);
    }

    /**
     * Process input rows, throwing on rows that match the failing set.
     *
     * @param Iterator|null $dataFrame
     * @return Iterator
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        if ($dataFrame === null) {
            return new ArrayIterator([]);
        }

        $results = [];
        foreach ($dataFrame as $row) {
            if (in_array($row, $this->failingRows, true)) {
                throw new RuntimeException("Processing failed for row: " . json_encode($row));
            }
            $results[] = $row;
        }

        return new ArrayIterator($results);
    }
}

/**
 * ErrorCallbackPropertyTest
 *
 * Property-based tests verifying that the global error callback receives
 * correct arguments for any stage exception under non-throw error strategies.
 *
 * Property 5: Global Error Callback Receives Correct Arguments
 *
 * For any stage exception under a non-throw error strategy, when an onError
 * callback is registered, the callback SHALL be invoked with exactly three
 * arguments: the thrown exception, the failing row data, and the stage name string.
 *
 * **Validates: Requirements 2.2**
 */
#[CoversClass(StageRunner::class)]
class ErrorCallbackPropertyTest extends TestCase
{
    /**
     * Data provider generating 50+ random row data sets for Skip strategy tests.
     *
     * @return Generator
     */
    public static function skipStrategyProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(2, 10);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    'id' => random_int(1, 10000),
                    'name' => 'user_' . bin2hex(random_bytes(random_int(2, 8))),
                    'value' => random_int(-1000, 1000),
                ];
            }
            // Pick a random row to fail
            $failIndex = random_int(0, $rowCount - 1);
            $stageName = 'stage_' . bin2hex(random_bytes(random_int(2, 6)));

            yield "rows={$rowCount},failIdx={$failIndex},i={$i}" => [$rows, $failIndex, $stageName];
        }
    }

    /**
     * Data provider generating 50+ random row data sets for Retry strategy tests.
     *
     * @return Generator
     */
    public static function retryStrategyProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(2, 10);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    'id' => random_int(1, 10000),
                    'email' => 'test_' . random_int(1, 9999) . '@example.com',
                    'score' => random_int(0, 100) / 10.0,
                ];
            }
            // Pick a random row to fail
            $failIndex = random_int(0, $rowCount - 1);
            $stageName = 'retry_stage_' . bin2hex(random_bytes(random_int(2, 4)));
            $maxAttempts = random_int(1, 3);

            yield "rows={$rowCount},failIdx={$failIndex},attempts={$maxAttempts},i={$i}" => [
                $rows, $failIndex, $stageName, $maxAttempts,
            ];
        }
    }

    /**
     * Data provider generating 50+ random row data sets for LogAndContinue strategy tests.
     *
     * @return Generator
     */
    public static function logAndContinueStrategyProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(2, 10);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    'id' => random_int(1, 10000),
                    'status' => ['active', 'inactive', 'pending'][random_int(0, 2)],
                    'amount' => random_int(100, 99999) / 100.0,
                ];
            }
            // Pick a random row to fail
            $failIndex = random_int(0, $rowCount - 1);
            $stageName = 'log_stage_' . bin2hex(random_bytes(random_int(2, 5)));

            yield "rows={$rowCount},failIdx={$failIndex},i={$i}" => [$rows, $failIndex, $stageName];
        }
    }

    /**
     * Property 5 (Skip): Global error callback receives correct arguments under Skip strategy.
     *
     * For any stage exception under the Skip error strategy, the onError callback
     * SHALL be invoked with exactly three arguments: the thrown exception (Throwable),
     * the failing row data (mixed), and the stage name (string).
     *
     * **Validates: Requirements 2.2**
     */
    #[Test]
    #[DataProvider('skipStrategyProvider')]
    public function errorCallbackReceivesCorrectArgumentsWithSkipStrategy(
        array  $rows,
        int    $failIndex,
        string $stageName,
    ): void
    {
        $failingRow = $rows[$failIndex];
        $processor = new ThrowingProcessor([$failingRow], $stageName);

        $callbackInvocations = [];
        $onError = function (Throwable $exception, mixed $row, string $stage) use (&$callbackInvocations) {
            $callbackInvocations[] = [
                'exception' => $exception,
                'row' => $row,
                'stageName' => $stage,
            ];
        };

        $stageRunner = new StageRunner();
        $input = new ArrayIterator($rows);
        $deadLetters = new DeadLetterCollection();
        $logger = new NullLogger();

        $output = $stageRunner->run(
            $processor,
            $input,
            ErrorStrategy::Skip,
            null,
            $logger,
            $deadLetters,
            $onError,
        );

        // Consume the generator to trigger processing
        iterator_to_array($output);

        // Verify callback was invoked exactly once for the failing row
        $this->assertCount(1, $callbackInvocations, 'onError callback should be invoked exactly once');

        $invocation = $callbackInvocations[0];

        // Verify argument 1: Throwable exception
        $this->assertInstanceOf(Throwable::class, $invocation['exception']);
        $this->assertStringContainsString(
            json_encode($failingRow),
            $invocation['exception']->getMessage(),
        );

        // Verify argument 2: The failing row data
        $this->assertSame($failingRow, $invocation['row']);

        // Verify argument 3: The stage name string
        $this->assertIsString($invocation['stageName']);
        $this->assertSame($stageName, $invocation['stageName']);
    }

    /**
     * Property 5 (Retry): Global error callback receives correct arguments under Retry strategy.
     *
     * For any stage exception under the Retry error strategy where all attempts are exhausted,
     * the onError callback SHALL be invoked with exactly three arguments: the thrown exception
     * (Throwable), the failing row data (mixed), and the stage name (string).
     *
     * **Validates: Requirements 2.2**
     */
    #[Test]
    #[DataProvider('retryStrategyProvider')]
    public function errorCallbackReceivesCorrectArgumentsWithRetryStrategy(
        array  $rows,
        int    $failIndex,
        string $stageName,
        int    $maxAttempts,
    ): void
    {
        $failingRow = $rows[$failIndex];
        $processor = new ThrowingProcessor([$failingRow], $stageName);

        $callbackInvocations = [];
        $onError = function (Throwable $exception, mixed $row, string $stage) use (&$callbackInvocations) {
            $callbackInvocations[] = [
                'exception' => $exception,
                'row' => $row,
                'stageName' => $stage,
            ];
        };

        $stageRunner = new StageRunner();
        $input = new ArrayIterator($rows);
        $deadLetters = new DeadLetterCollection();
        $logger = new NullLogger();
        $retryConfig = new RetryConfig(maxAttempts: $maxAttempts, delay: 0);

        $output = $stageRunner->run(
            $processor,
            $input,
            ErrorStrategy::Retry,
            $retryConfig,
            $logger,
            $deadLetters,
            $onError,
        );

        // Consume the generator to trigger processing
        iterator_to_array($output);

        // Verify callback was invoked exactly once for the failing row (after all retries exhausted)
        $this->assertCount(1, $callbackInvocations, 'onError callback should be invoked exactly once after retry exhaustion');

        $invocation = $callbackInvocations[0];

        // Verify argument 1: Throwable exception
        $this->assertInstanceOf(Throwable::class, $invocation['exception']);

        // Verify argument 2: The failing row data
        $this->assertSame($failingRow, $invocation['row']);

        // Verify argument 3: The stage name string
        $this->assertIsString($invocation['stageName']);
        $this->assertSame($stageName, $invocation['stageName']);
    }

    /**
     * Property 5 (LogAndContinue): Global error callback receives correct arguments under LogAndContinue strategy.
     *
     * For any stage exception under the LogAndContinue error strategy, the onError callback
     * SHALL be invoked with exactly three arguments: the thrown exception (Throwable),
     * the failing row data (mixed), and the stage name (string).
     *
     * **Validates: Requirements 2.2**
     */
    #[Test]
    #[DataProvider('logAndContinueStrategyProvider')]
    public function errorCallbackReceivesCorrectArgumentsWithLogAndContinueStrategy(
        array  $rows,
        int    $failIndex,
        string $stageName,
    ): void
    {
        $failingRow = $rows[$failIndex];
        $processor = new ThrowingProcessor([$failingRow], $stageName);

        $callbackInvocations = [];
        $onError = function (Throwable $exception, mixed $row, string $stage) use (&$callbackInvocations) {
            $callbackInvocations[] = [
                'exception' => $exception,
                'row' => $row,
                'stageName' => $stage,
            ];
        };

        $stageRunner = new StageRunner();
        $input = new ArrayIterator($rows);
        $deadLetters = new DeadLetterCollection();
        $logger = new NullLogger();

        $output = $stageRunner->run(
            $processor,
            $input,
            ErrorStrategy::LogAndContinue,
            null,
            $logger,
            $deadLetters,
            $onError,
        );

        // Consume the generator to trigger processing
        iterator_to_array($output);

        // Verify callback was invoked exactly once for the failing row
        $this->assertCount(1, $callbackInvocations, 'onError callback should be invoked exactly once');

        $invocation = $callbackInvocations[0];

        // Verify argument 1: Throwable exception
        $this->assertInstanceOf(Throwable::class, $invocation['exception']);
        $this->assertStringContainsString(
            json_encode($failingRow),
            $invocation['exception']->getMessage(),
        );

        // Verify argument 2: The failing row data
        $this->assertSame($failingRow, $invocation['row']);

        // Verify argument 3: The stage name string
        $this->assertIsString($invocation['stageName']);
        $this->assertSame($stageName, $invocation['stageName']);
    }
}
