
# Metrics Exporter

Emit real-time pipeline metrics to external systems (logging, monitoring, custom
callbacks) via the `MetricsExporter` interface.

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Metrics\LogMetricsExporter;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('metrics');
$logger->pushHandler(new StreamHandler('metrics.log'));

$result = (new DataFlow())
    ->from($records)
    ->withMetricsExporter(new LogMetricsExporter($logger))
    ->transform(fn($row) => processRow($row))
    ->load(fn($row) => saveRow($row))
    ->run();
```

## Available Exporters

### LogMetricsExporter

Writes structured log entries via PSR-3.

```php
use Simsoft\DataFlow\Metrics\LogMetricsExporter;

$exporter = new LogMetricsExporter($psrLogger);
```

| Event             | Log Level | Context Keys                                                  |
|-------------------|-----------|---------------------------------------------------------------|
| Row processed     | info      | `stage`, `event`                                              |
| Row failed        | warning   | `stage`, `error`, `event`                                     |
| Stage duration    | info      | `stage`, `duration_ms`, `event`                               |
| Pipeline complete | info      | `total_duration_ms`, `processed_rows`, `failed_rows`, `event` |

### CallbackMetricsExporter

Hook into specific events with closures.

```php
use Simsoft\DataFlow\Metrics\CallbackMetricsExporter;

$exporter = new CallbackMetricsExporter(
    onRowProcessed: function (string $stageName) {
        StatsD::increment("pipeline.rows.processed", tags: ["stage:{$stageName}"]);
    },
    onRowFailed: function (string $stageName, string $error) {
        StatsD::increment("pipeline.rows.failed", tags: ["stage:{$stageName}"]);
    },
    onStageDuration: function (string $stageName, float $durationMs) {
        StatsD::timing("pipeline.stage.duration", $durationMs, tags: ["stage:{$stageName}"]);
    },
    onPipelineComplete: function (float $totalMs, int $processed, int $failed) {
        StatsD::timing("pipeline.total.duration", $totalMs);
        StatsD::gauge("pipeline.total.processed", $processed);
    },
);

(new DataFlow())
    ->from($records)
    ->withMetricsExporter($exporter)
    ->transform(fn($row) => $row)
    ->load(fn($row) => $row)
    ->run();
```

All closures are optional — pass only the events you care about.

### NullMetricsExporter

The default when no exporter is configured. Zero overhead (empty method bodies).

## Custom Exporter

Implement the `MetricsExporter` interface for custom integrations.

```php
use Simsoft\DataFlow\Interfaces\MetricsExporter;

class PrometheusExporter implements MetricsExporter
{
    public function recordRowProcessed(string $stageName): void
    {
        // increment counter
    }

    public function recordRowFailed(string $stageName, string $errorMessage): void
    {
        // increment error counter with labels
    }

    public function recordStageDuration(string $stageName, float $durationMs): void
    {
        // observe histogram
    }

    public function recordPipelineComplete(float $totalDurationMs, int $processedRows, int $failedRows): void
    {
        // record summary gauge
    }
}
```

## Metrics vs Observability

| Feature                 | Purpose                                      | Doc                                     |
|-------------------------|----------------------------------------------|-----------------------------------------|
| `withLogger()`          | PSR-3 logging of stage events                | [08-OBSERVABILITY](08-OBSERVABILITY.md) |
| `onProgress()`          | Progress callback during execution           | [08-OBSERVABILITY](08-OBSERVABILITY.md) |
| `PipelineResult`        | Post-execution summary                       | [08-OBSERVABILITY](08-OBSERVABILITY.md) |
| `withMetricsExporter()` | Real-time event emission to external systems | This doc                                |

The metrics exporter fires events as rows flow through the pipeline (real-time),
while `PipelineResult` provides a summary after execution completes.
