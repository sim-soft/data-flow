<?php

namespace Simsoft\DataFlow\Metrics;

use Closure;
use Simsoft\DataFlow\Interfaces\MetricsExporter;

/**
 * CallbackMetricsExporter - Invokes user-supplied closures for each metric event.
 *
 * Allows hooking into specific metrics events with custom logic
 * without implementing the full MetricsExporter interface.
 */
final class CallbackMetricsExporter implements MetricsExporter
{
    public function __construct(
        private ?Closure $onRowProcessed = null,
        private ?Closure $onRowFailed = null,
        private ?Closure $onStageDuration = null,
        private ?Closure $onPipelineComplete = null,
    )
    {
    }

    public function recordRowProcessed(string $stageName): void
    {
        if ($this->onRowProcessed !== null) {
            ($this->onRowProcessed)($stageName);
        }
    }

    public function recordRowFailed(string $stageName, string $errorMessage): void
    {
        if ($this->onRowFailed !== null) {
            ($this->onRowFailed)($stageName, $errorMessage);
        }
    }

    public function recordStageDuration(string $stageName, float $durationMs): void
    {
        if ($this->onStageDuration !== null) {
            ($this->onStageDuration)($stageName, $durationMs);
        }
    }

    public function recordPipelineComplete(float $totalDurationMs, int $processedRows, int $failedRows): void
    {
        if ($this->onPipelineComplete !== null) {
            ($this->onPipelineComplete)($totalDurationMs, $processedRows, $failedRows);
        }
    }
}
