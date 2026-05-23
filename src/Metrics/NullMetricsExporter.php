<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Metrics;

use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Throwable;

/**
 * NullMetricsExporter - No-op implementation of MetricsExporter.
 *
 * Used as the default when no metrics exporter is configured,
 * providing zero overhead via the null object pattern.
 */
final class NullMetricsExporter implements MetricsExporter
{
    public function recordRowProcessed(string $stageName): void
    {
    }

    public function recordRowFailed(string $stageName, Throwable $error): void
    {
    }

    public function recordStageDuration(string $stageName, float $durationMs): void
    {
    }

    public function recordPipelineComplete(float $totalDurationMs, int $processedRows, int $failedRows): void
    {
    }
}
