---
title: Logging & Metrics
parent: Observability
nav_order: 1
---

# Observability

The DataFlow library supports PSR-3 logging, pipeline metrics, and progress
tracking for production visibility.

## PSR-3 Logger Integration

Inject any PSR-3 compatible logger to capture pipeline activity.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Simsoft\DataFlow\DataFlow;

$logger = new Logger('etl');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$logger->pushHandler(new StreamHandler('pipeline.log', Logger::WARNING));

$result = (new DataFlow())
    ->from($records)
    ->withLogger($logger)
    ->transform(fn($row) => processRow($row))
    ->load(fn($row) => saveRow($row))
    ->run();
```

### What Gets Logged

| Level     | Message                                                |
|-----------|--------------------------------------------------------|
| `debug`   | Stage started (with stage name)                        |
| `info`    | Stage completed (with stage name and row count)        |
| `error`   | Exception caught (with stage name, message, row index) |
| `warning` | Row failure details (row index, stage name, message)   |
| `debug`   | Row data context (only at debug level for privacy)     |

### Example Log Output

```
[2025-03-15 10:30:01] etl.DEBUG: Stage 'csv-reader' started
[2025-03-15 10:30:02] etl.INFO: Stage 'csv-reader' completed: 1500 rows processed
[2025-03-15 10:30:02] etl.DEBUG: Stage 'validator' started
[2025-03-15 10:30:02] etl.ERROR: Stage 'validator' exception: Invalid email format at row 42
[2025-03-15 10:30:02] etl.WARNING: Row 42 failed in stage 'validator': Invalid email format
[2025-03-15 10:30:03] etl.INFO: Stage 'validator' completed: 1499 rows processed
```

Row data is only included in debug-level context, keeping warning/error logs
free of potentially sensitive information.

## Pipeline Result

Every `run()` call returns a `PipelineResult` object with comprehensive
execution metadata.

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($records)
    ->transform(fn($row) => $row)
    ->load(fn($row) => $row)
    ->run();

// Timing
echo "Started: " . $result->getStartTime()->format('Y-m-d H:i:s') . "\n";
echo "Ended: " . $result->getEndTime()->format('Y-m-d H:i:s') . "\n";
echo "Duration: " . round($result->getDurationMs()) . "ms\n";

// Row counts
echo "Processed: {$result->getProcessedRows()}\n";
echo "Failed: {$result->getFailedRows()}\n";

// Resources
echo "Peak memory: " . round($result->getPeakMemoryBytes() / 1024 / 1024, 1) . " MB\n";

// Mode
echo "Dry run: " . ($result->isDryRun() ? 'yes' : 'no') . "\n";
```

### Serialization

Convert the result to an array for JSON APIs, logging, or storage.

```php
$result = (new DataFlow())->from($data)->load(fn($r) => $r)->run();

$array = $result->toArray();
// Returns:
// [
//     'startTime' => '2025-03-15T10:30:00+00:00',
//     'endTime' => '2025-03-15T10:30:05+00:00',
//     'processedRows' => 1500,
//     'failedRows' => 3,
//     'durationMs' => 5023.45,
//     'peakMemoryBytes' => 8388608,
//     'isDryRun' => false,
//     'stageMetrics' => [...],
//     'deadLetters' => ['count' => 3, 'entries' => [...]],
//     'failures' => [...],
// ]

// Send to monitoring system
$json = json_encode($array, JSON_PRETTY_PRINT);
```

## Per-Stage Metrics

Get timing and row count breakdowns for each stage in the pipeline.

```php
$result = (new DataFlow())
    ->from($records)
    ->transform((new Validator())->withName('validator'))
    ->transform((new Enricher())->withName('enricher'))
    ->load((new DbWriter())->withName('db-writer'))
    ->run();

foreach ($result->getStageMetrics() as $metrics) {
    printf(
        "  %s: %d rows in, %d rows out, %.1fms\n",
        $metrics->stageName,
        $metrics->rowsEntered,
        $metrics->rowsExited,
        $metrics->durationMs,
    );
}

// Output:
//   validator: 1500 rows in, 1500 rows out, 120.3ms
//   enricher: 1500 rows in, 1500 rows out, 3450.7ms
//   db-writer: 1500 rows in, 1500 rows out, 890.2ms
```

## Progress Tracking

Monitor pipeline progress with a callback invoked at configurable intervals.

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($largeDataset) // 100,000 rows
    ->onProgress(function (int $rowCount, float $elapsedMs) {
        $seconds = round($elapsedMs / 1000, 1);
        echo "\r  Processed {$rowCount} rows ({$seconds}s)...";
    }, interval: 1000) // invoke every 1000 rows
    ->transform(fn($row) => processRow($row))
    ->load(fn($row) => saveRow($row))
    ->run();

echo "\nDone! {$result->getProcessedRows()} rows in "
    . round($result->getDurationMs() / 1000, 1) . "s\n";

// Output:
//   Processed 1000 rows (0.5s)...
//   Processed 2000 rows (1.1s)...
//   ...
//   Processed 100000 rows (52.3s)...
// Done! 100000 rows in 52.3s
```

### Progress with Percentage

```php
$totalRows = count($records);

(new DataFlow())
    ->from($records)
    ->onProgress(function (int $rowCount, float $elapsedMs) use ($totalRows) {
        $percent = round(($rowCount / $totalRows) * 100);
        $rate = $rowCount / ($elapsedMs / 1000);
        echo "\r  [{$percent}%] {$rowCount}/{$totalRows} rows (" . round($rate) . " rows/s)";
    }, interval: 500)
    ->transform(fn($row) => $row)
    ->load(fn($row) => $row)
    ->run();
```

## Combining Observability Features

A production pipeline with full observability.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$logger = new Logger('etl');
$logger->pushHandler(new StreamHandler('etl.log', Logger::INFO));

$errors = [];

$result = (new DataFlow())
    ->from($csvRecords)
    ->withLogger($logger)
    ->onError(function (\Throwable $e, mixed $row, string $stage) use (&$errors) {
        $errors[] = compact('stage', 'row') + ['error' => $e->getMessage()];
    })
    ->onProgress(function (int $count, float $ms) {
        echo "\r  Processing... {$count} rows";
    }, interval: 100)
    ->transform(
        (new Validator())->withErrorStrategy(ErrorStrategy::Skip)->withName('validator')
    )
    ->transform(
        (new Enricher())->withRetry(3, 200)->withName('enricher')
    )
    ->load(
        (new DbWriter())->withErrorStrategy(ErrorStrategy::LogAndContinue)->withName('db-writer')
    )
    ->run();

echo "\n\n=== Pipeline Summary ===\n";
echo "Duration: " . round($result->getDurationMs() / 1000, 2) . "s\n";
echo "Processed: {$result->getProcessedRows()}\n";
echo "Failed: {$result->getFailedRows()}\n";
echo "Peak memory: " . round($result->getPeakMemoryBytes() / 1024 / 1024, 1) . " MB\n";

if (count($errors) > 0) {
    echo "\nErrors:\n";
    foreach ($errors as $err) {
        echo "  [{$err['stage']}] {$err['error']}\n";
    }
}
```
