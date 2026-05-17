<?php

namespace Simsoft\DataFlow\Metrics;

use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Interfaces\MetricsExporter;

/**
 * LogMetricsExporter - Writes pipeline metrics as structured log entries via PSR-3.
 */
final class LogMetricsExporter implements MetricsExporter
{
    public function __construct(
        private readonly LoggerInterface $logger,
    )
    {
    }

    public function recordRowProcessed(string $stageName): void
    {
        $this->logger->info('Row processed', [
            'stage' => $stageName,
            'event' => 'row_processed',
        ]);
    }

    public function recordRowFailed(string $stageName, string $errorMessage): void
    {
        $this->logger->warning('Row failed', [
            'stage' => $stageName,
            'error' => $errorMessage,
            'event' => 'row_failed',
        ]);
    }

    public function recordStageDuration(string $stageName, float $durationMs): void
    {
        $this->logger->info('Stage completed', [
            'stage' => $stageName,
            'duration_ms' => $durationMs,
            'event' => 'stage_duration',
        ]);
    }

    public function recordPipelineComplete(float $totalDurationMs, int $processedRows, int $failedRows): void
    {
        $this->logger->info('Pipeline complete', [
            'total_duration_ms' => $totalDurationMs,
            'processed_rows' => $processedRows,
            'failed_rows' => $failedRows,
            'event' => 'pipeline_complete',
        ]);
    }
}
