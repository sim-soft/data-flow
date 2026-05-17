<?php

namespace Simsoft\DataFlow\Interfaces;

/**
 * MetricsExporter Interface
 */
interface MetricsExporter
{
    /**
     * Record a row successfully processed by a stage.
     *
     * @param string $stageName
     * @return void
     */
    public function recordRowProcessed(string $stageName): void;

    /**
     * Record a row that failed processing in a stage.
     *
     * @param string $stageName
     * @param string $errorMessage
     * @return void
     */
    public function recordRowFailed(string $stageName, string $errorMessage): void;

    /**
     * Record the duration of a stage's execution.
     *
     * @param string $stageName
     * @param float $durationMs
     * @return void
     */
    public function recordStageDuration(string $stageName, float $durationMs): void;

    /**
     * Record pipeline completion with summary metrics.
     *
     * @param float $totalDurationMs
     * @param int $processedRows
     * @param int $failedRows
     * @return void
     */
    public function recordPipelineComplete(float $totalDurationMs, int $processedRows, int $failedRows): void;
}
