<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

use DateTimeImmutable;
use Generator;
use Iterator;
use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Metrics\NullMetricsExporter;

/**
 * PipelineExecutor
 *
 * Orchestrates pipeline stage execution via StageRunner, collecting per-stage
 * metrics, tracking progress, and building the PipelineResult.
 */
final class PipelineExecutor
{
    /** @var StageRunner The stage runner instance. */
    private StageRunner $stageRunner;

    /**
     * Constructor.
     *
     * @param LoggerInterface $logger PSR-3 logger instance.
     * @param DeadLetterCollection $deadLetters Collection for failed rows.
     * @param callable|null $onError Global error callback.
     * @param callable|null $onProgress Progress callback.
     * @param int $progressInterval Rows between progress callback invocations.
     * @param bool $dryRun Whether to run in dry-run mode.
     * @param CheckpointManager|null $checkpointManager Checkpoint manager for crash recovery.
     * @param bool $shouldResume Whether to resume from last checkpoint.
     * @param MetricsExporter $metricsExporter Metrics exporter for observability.
     */
    public function __construct(
        private LoggerInterface      $logger,
        private DeadLetterCollection $deadLetters,
        private mixed                $onError = null,
        private mixed                $onProgress = null,
        private int                  $progressInterval = 100,
        private bool                 $dryRun = false,
        private ?CheckpointManager   $checkpointManager = null,
        private bool                 $shouldResume = false,
        private MetricsExporter      $metricsExporter = new NullMetricsExporter(),
    )
    {
        $this->stageRunner = new StageRunner();
    }

    /**
     * Execute the full pipeline and return metrics.
     *
     * Invokes each stage through StageRunner for per-stage error handling and metrics.
     * For stages with the default Throw strategy, invokes directly to preserve
     * full-stream semantics (required for stateful transformers like Chunk).
     * For stages with non-throw strategies, uses StageRunner for per-row error isolation.
     *
     * Integrates checkpoint/resume logic, schema validation, and real-time metrics emission.
     *
     * @param Processor[] $stages The ordered list of pipeline stages.
     *
     * @return PipelineResult
     */
    public function execute(array $stages): PipelineResult
    {
        $startTime = new DateTimeImmutable();
        $elapsedStartNs = hrtime(true);

        // Generate pipeline ID for checkpoint operations
        $pipelineId = $this->generatePipelineId($stages);

        // Determine resume skip point
        $skipToRowIndex = $this->determineResumePoint($pipelineId);

        // Set dry-run flag on loaders before execution
        foreach ($stages as $stage) {
            if ($this->dryRun && $stage instanceof Loader) {
                $stage->setDryRun(true);
            }
        }

        /** @var StageMetrics[] $stageMetrics */
        $stageMetrics = [];
        $totalRowsProcessed = 0;

        // Build the stage pipeline: each stage receives the output of the previous
        $currentIterator = null;

        foreach ($stages as $index => $stage) {
            $stageStartNs = hrtime(true);

            // Determine how to invoke the stage based on error strategy
            $stageOutput = $this->invokeStage($stage, $currentIterator);

            if ($index < count($stages) - 1) {
                // Intermediate stage: wrap output to collect metrics lazily
                $currentIterator = $this->createMetricsCollectingIterator(
                    $stageOutput,
                    $stage,
                    $stageStartNs,
                    $stageMetrics,
                );
                continue;
            }

            // Last stage: consume output, track progress, collect metrics, handle checkpoint
            $rowsExited = $this->consumeLastStageWithFeatures(
                $stageOutput,
                $totalRowsProcessed,
                $elapsedStartNs,
                $stage->getName(),
                $pipelineId,
                $skipToRowIndex,
            );

            $stageDurationMs = (hrtime(true) - $stageStartNs) / 1_000_000;
            $stageMetrics[] = new StageMetrics(
                stageName: $stage->getName(),
                rowsEntered: $rowsExited,
                rowsExited: $rowsExited,
                durationMs: $stageDurationMs,
            );

            // Record stage duration metric
            $this->metricsExporter->recordStageDuration($stage->getName(), $stageDurationMs);
        }

        // Record stage durations for intermediate stages (already collected in stageMetrics)
        // Note: intermediate stage durations are recorded when their metrics-collecting iterators complete
        // The last stage duration is recorded above

        // Handle empty pipeline (no stages)
        if (empty($stages)) {
            $totalRowsProcessed = 0;
        }

        // Delete checkpoint on successful completion
        if ($this->checkpointManager !== null) {
            $this->checkpointManager->delete();
        }

        return $this->buildResult($startTime, $elapsedStartNs, $totalRowsProcessed, $stageMetrics);
    }

    /**
     * Build the final PipelineResult with timing and metrics.
     *
     * @param DateTimeImmutable $startTime Pipeline start time.
     * @param int|float $elapsedStartNs Pipeline start in nanoseconds.
     * @param int $totalRowsProcessed Total rows processed.
     * @param StageMetrics[] $stageMetrics Per-stage metrics.
     *
     * @return PipelineResult
     */
    private function buildResult(
        DateTimeImmutable $startTime,
        int|float         $elapsedStartNs,
        int               $totalRowsProcessed,
        array             $stageMetrics,
    ): PipelineResult
    {
        $endTime = new DateTimeImmutable();
        $durationMs = (hrtime(true) - $elapsedStartNs) / 1_000_000;
        $peakMemory = memory_get_peak_usage(true);

        $failedRows = $this->deadLetters->count();

        // Record pipeline completion metric
        $this->metricsExporter->recordPipelineComplete($durationMs, $totalRowsProcessed, $failedRows);

        return new PipelineResult(
            startTime: $startTime,
            endTime: $endTime,
            processedRows: $totalRowsProcessed,
            failedRows: $failedRows,
            durationMs: $durationMs,
            peakMemoryBytes: $peakMemory,
            isDryRun: $this->dryRun,
            stageMetrics: $stageMetrics,
            deadLetters: $this->deadLetters,
            failures: $this->buildFailures(),
            circuitStates: $this->stageRunner->getCircuitStates(),
        );
    }

    /**
     * Generate a deterministic pipeline ID from stage names.
     *
     * Delegates to {@see CheckpointManager::generatePipelineId()} to ensure a
     * single source of truth for the hashing algorithm used by the pipeline
     * and the checkpoint manager.
     *
     * @param Processor[] $stages
     * @return string
     */
    private function generatePipelineId(array $stages): string
    {
        $stageNames = array_map(
            static fn(Processor $stage): string => $stage->getName(),
            $stages,
        );

        return CheckpointManager::generatePipelineId($stageNames);
    }

    /**
     * Determine the row index to skip to based on checkpoint resume logic.
     *
     * @param string $pipelineId The current pipeline ID.
     * @return int The row index to skip to (-1 means no skipping).
     */
    private function determineResumePoint(string $pipelineId): int
    {
        if (!$this->shouldResume || $this->checkpointManager === null) {
            return -1;
        }

        $checkpoint = $this->checkpointManager->read();

        if ($checkpoint === null) {
            return -1;
        }

        if ($checkpoint->pipelineId !== $pipelineId) {
            $this->logger->warning(
                'Checkpoint pipeline ID mismatch: expected {expected}, found {found}. Starting from beginning.',
                ['expected' => $pipelineId, 'found' => $checkpoint->pipelineId],
            );
            return -1;
        }

        $this->logger->info(
            'Resuming pipeline from row {rowIndex}',
            ['rowIndex' => $checkpoint->lastRowIndex],
        );

        return $checkpoint->lastRowIndex;
    }

    /**
     * Invoke a stage with appropriate error handling.
     *
     * For stages with the Throw strategy, invokes directly with the full input
     * iterator to preserve streaming semantics (required for stateful transformers).
     * For stages with non-throw strategies, uses StageRunner for per-row error isolation.
     *
     * @param Processor $stage The stage to invoke.
     * @param Iterator|null $input The input iterator (null for extractors).
     *
     * @return Generator The stage output iterator.
     */
    private function invokeStage(Processor $stage, ?Iterator $input): Generator
    {
        $strategy = $stage->getErrorStrategy();

        if ($strategy !== ErrorStrategy::Throw || $input === null) {
            // Use StageRunner for:
            // - Extractor stages (null input) - StageRunner handles extractor iteration with error handling
            // - Non-throw strategies - StageRunner provides per-row error isolation
            return $this->stageRunner->run(
                stage: $stage,
                input: $input,
                strategy: $strategy,
                retryConfig: $stage->getRetryConfig(),
                logger: $this->logger,
                deadLetters: $this->deadLetters,
                onError: $this->onError,
                metricsExporter: $this->metricsExporter,
            );
        }

        // Throw strategy with input: invoke stage directly with full input
        // to preserve streaming semantics for stateful transformers (e.g., Chunk)
        return $this->invokeDirectly($stage, $input);
    }

    /**
     * Invoke a stage directly with the full input iterator.
     *
     * Logs stage boundaries and propagates any exceptions immediately.
     * Used for stages with the Throw error strategy to preserve full-stream semantics.
     *
     * @param Processor $stage The stage to invoke.
     * @param Iterator $input The full input iterator.
     *
     * @return Generator<int, mixed, mixed, void>
     */
    private function invokeDirectly(Processor $stage, Iterator $input): Generator
    {
        $stageName = $stage->getName();
        $this->logger->debug("Stage '{$stageName}' started");

        $rowCount = 0;
        $output = $stage($input);

        foreach ($output as $row) {
            $rowCount++;
            yield $row;
        }

        $this->logger->info("Stage '{$stageName}' completed: {$rowCount} rows processed");
    }

    /**
     * Create an iterator that collects stage metrics when consumed.
     *
     * Since generators are lazy, metrics for intermediate stages are collected
     * as rows flow through. This wraps the stage output and records metrics
     * into the stageMetrics array once the stage output is fully consumed.
     * Also emits real-time metrics via the MetricsExporter.
     *
     * @param Generator $stageOutput The stage output generator.
     * @param Processor $stage The stage processor.
     * @param int|float $stageStartNs The stage start time in nanoseconds.
     * @param StageMetrics[] &$stageMetrics Reference to the stage metrics array.
     *
     * @return Generator<int, mixed, mixed, void>
     */
    private function createMetricsCollectingIterator(
        Generator $stageOutput,
        Processor $stage,
        int|float $stageStartNs,
        array     &$stageMetrics,
    ): Generator
    {
        $rowsExited = 0;
        $stageName = $stage->getName();

        foreach ($stageOutput as $row) {
            $rowsExited++;
            $this->metricsExporter->recordRowProcessed($stageName);
            yield $row;
        }

        $stageDurationMs = (hrtime(true) - $stageStartNs) / 1_000_000;

        $stageMetrics[] = new StageMetrics(
            stageName: $stageName,
            rowsEntered: $rowsExited,
            rowsExited: $rowsExited,
            durationMs: $stageDurationMs,
        );

        $this->metricsExporter->recordStageDuration($stageName, $stageDurationMs);
    }

    /**
     * Consume the last stage output with checkpoint, metrics, and progress tracking.
     *
     * @param Generator $stageOutput The stage output generator.
     * @param int        &$totalRowsProcessed Running total of processed rows.
     * @param int|float $elapsedStartNs Pipeline start time in nanoseconds.
     * @param string $stageName The last stage name.
     * @param string $pipelineId The pipeline ID for checkpointing.
     * @param int $skipToRowIndex Row index to skip to (-1 means no skipping).
     *
     * @return int The number of rows consumed.
     */
    private function consumeLastStageWithFeatures(
        Generator $stageOutput,
        int       &$totalRowsProcessed,
        int|float $elapsedStartNs,
        string    $stageName,
        string    $pipelineId,
        int       $skipToRowIndex,
    ): int
    {
        $rowsExited = 0;
        $globalRowIndex = 0;

        while ($stageOutput->valid()) {
            $globalRowIndex++;

            // Checkpoint resume: skip rows up to and including lastRowIndex
            if ($skipToRowIndex >= 0 && $globalRowIndex <= $skipToRowIndex) {
                $stageOutput->next();
                continue;
            }

            $rowsExited++;
            $totalRowsProcessed++;

            // Record row processed metric for the last stage
            $this->metricsExporter->recordRowProcessed($stageName);

            // Checkpoint writing
            if ($this->checkpointManager !== null && $this->checkpointManager->shouldWrite($globalRowIndex)) {
                $this->checkpointManager->write($pipelineId, $globalRowIndex, $stageName);
            }

            if ($this->onProgress !== null && ($totalRowsProcessed % $this->progressInterval) === 0) {
                $elapsedMs = (hrtime(true) - $elapsedStartNs) / 1_000_000;
                ($this->onProgress)($totalRowsProcessed, $elapsedMs);
            }

            $stageOutput->next();
        }

        // Fire progress callback for tail rows
        if ($this->onProgress !== null && $totalRowsProcessed > 0 && ($totalRowsProcessed % $this->progressInterval) !== 0) {
            $elapsedMs = (hrtime(true) - $elapsedStartNs) / 1_000_000;
            ($this->onProgress)($totalRowsProcessed, $elapsedMs);
        }

        return $rowsExited;
    }

    /**
     * Build failure records from the dead-letter collection.
     *
     * @return array{row: mixed, stageName: string, message: string, rowIndex: int}[]
     */
    private function buildFailures(): array
    {
        $failures = [];

        foreach ($this->deadLetters as $entry) {
            $failures[] = [
                'row' => $entry->row,
                'stageName' => $entry->stageName,
                'message' => $entry->exception->getMessage(),
                'rowIndex' => $entry->rowIndex,
            ];
        }

        return $failures;
    }
}
