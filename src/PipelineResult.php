<?php

namespace Simsoft\DataFlow;

use DateTimeImmutable;
use Simsoft\DataFlow\Enums\CircuitState;

/**
 * PipelineResult
 *
 * Contains the complete metrics and outcome data from a pipeline execution.
 * Provides access to timing, row counts, per-stage metrics, dead-letter entries,
 * and failure records. Supports serialization to an associative array.
 */
final class PipelineResult
{
    /**
     * @param DateTimeImmutable $startTime Pipeline execution start time.
     * @param DateTimeImmutable $endTime Pipeline execution end time.
     * @param int $processedRows Total rows that passed through all stages successfully.
     * @param int $failedRows Total rows that failed processing.
     * @param float $durationMs Total execution duration in milliseconds.
     * @param int $peakMemoryBytes Peak memory usage in bytes during the run.
     * @param bool $isDryRun Whether this was a dry-run execution.
     * @param StageMetrics[] $stageMetrics Per-stage timing and row count metrics.
     * @param DeadLetterCollection $deadLetters Collection of rows that failed processing.
     * @param array<int, array{row: mixed, stageName: string, message: string, rowIndex: int}> $failures Failure detail records.
     * @param array<string, CircuitState> $circuitStates Final circuit state per stage (only for stages with CB configured).
     */
    public function __construct(
        private DateTimeImmutable    $startTime,
        private DateTimeImmutable    $endTime,
        private int                  $processedRows,
        private int                  $failedRows,
        private float                $durationMs,
        private int                  $peakMemoryBytes,
        private bool                 $isDryRun,
        /** @var StageMetrics[] */
        private array                $stageMetrics,
        private DeadLetterCollection $deadLetters,
        /** @var array<int, array{row: mixed, stageName: string, message: string, rowIndex: int}> */
        private array                $failures,
        /** @var array<string, CircuitState> */
        private array                $circuitStates = [],
    )
    {
    }

    /**
     * Get the pipeline execution start time.
     *
     * @return DateTimeImmutable
     */
    public function getStartTime(): DateTimeImmutable
    {
        return $this->startTime;
    }

    /**
     * Get the pipeline execution end time.
     *
     * @return DateTimeImmutable
     */
    public function getEndTime(): DateTimeImmutable
    {
        return $this->endTime;
    }

    /**
     * Get the total number of rows that passed through all stages successfully.
     *
     * @return int
     */
    public function getProcessedRows(): int
    {
        return $this->processedRows;
    }

    /**
     * Get the total number of rows that failed processing.
     *
     * @return int
     */
    public function getFailedRows(): int
    {
        return $this->failedRows;
    }

    /**
     * Get the total execution duration in milliseconds.
     *
     * @return float
     */
    public function getDurationMs(): float
    {
        return $this->durationMs;
    }

    /**
     * Get the peak memory usage in bytes during the pipeline run.
     *
     * @return int
     */
    public function getPeakMemoryBytes(): int
    {
        return $this->peakMemoryBytes;
    }

    /**
     * Check whether this was a dry-run execution.
     *
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->isDryRun;
    }

    /**
     * Get per-stage timing and row count metrics.
     *
     * @return StageMetrics[]
     */
    public function getStageMetrics(): array
    {
        return $this->stageMetrics;
    }

    /**
     * Get the dead-letter collection containing rows that failed processing.
     *
     * @return DeadLetterCollection
     */
    public function getDeadLetters(): DeadLetterCollection
    {
        return $this->deadLetters;
    }

    /**
     * Get the list of failure detail records.
     *
     * @return array<int, array{row: mixed, stageName: string, message: string, rowIndex: int}>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * Get the final circuit state for each stage that has a circuit breaker configured.
     *
     * @return array<string, CircuitState>
     */
    public function getCircuitStates(): array
    {
        return $this->circuitStates;
    }

    /**
     * Serialize the pipeline result to an associative array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'startTime' => $this->startTime->format('c'),
            'endTime' => $this->endTime->format('c'),
            'processedRows' => $this->processedRows,
            'failedRows' => $this->failedRows,
            'durationMs' => $this->durationMs,
            'peakMemoryBytes' => $this->peakMemoryBytes,
            'isDryRun' => $this->isDryRun,
            'stageMetrics' => array_map(
                static fn(StageMetrics $metrics): array => [
                    'stageName' => $metrics->stageName,
                    'rowsEntered' => $metrics->rowsEntered,
                    'rowsExited' => $metrics->rowsExited,
                    'durationMs' => $metrics->durationMs,
                ],
                $this->stageMetrics,
            ),
            'deadLetters' => [
                'count' => $this->deadLetters->count(),
                'entries' => array_map(
                    static fn(DeadLetterEntry $entry): array => [
                        'row' => $entry->row,
                        'stageName' => $entry->stageName,
                        'rowIndex' => $entry->rowIndex,
                        'exception' => $entry->exception->getMessage(),
                        'occurredAt' => $entry->occurredAt->format('c'),
                    ],
                    $this->deadLetters->toArray(),
                ),
            ],
            'failures' => $this->failures,
            'circuitStates' => array_map(
                static fn(CircuitState $state): string => $state->value,
                $this->circuitStates,
            ),
        ];
    }
}
