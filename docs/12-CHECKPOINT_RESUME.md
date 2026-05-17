---
title: Checkpoint & Resume
parent: Resilience
nav_order: 3
---

# Checkpoint & Resume

Enable crash recovery for long-running pipelines. Checkpoints save progress to a
file at configurable intervals. If the pipeline crashes, it can resume from the
last checkpoint.

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($millionRows)
    ->withCheckpoint('/tmp/pipeline-checkpoint.json', interval: 1000)
    ->transform(fn($row) => processRow($row))
    ->load(fn($row) => saveRow($row))
    ->run();
```

A checkpoint file is written every 1000 rows. On successful completion, the
checkpoint file is automatically deleted.

## Resuming After Crash

```php
$result = (new DataFlow())
    ->from($millionRows)
    ->withCheckpoint('/tmp/pipeline-checkpoint.json', interval: 1000)
    ->resume()  // skip rows already processed
    ->transform(fn($row) => processRow($row))
    ->load(fn($row) => saveRow($row))
    ->run();
```

When `resume()` is called, the pipeline reads the checkpoint file and skips rows
up to the last saved position.

## How It Works

1. **Pipeline ID** — A deterministic SHA-256 hash is generated from stage names.
   This ensures the checkpoint matches the current pipeline configuration.
2. **Interval writes** — Every N rows (configurable), the current position is
   atomically written to the checkpoint file using a temp-file + rename
   pattern (crash-safe).
3. **Resume** — On startup with `resume()`, the checkpoint is read. If the
   pipeline ID matches, rows up to `lastRowIndex` are skipped.
4. **Cleanup** — On successful completion, the checkpoint file is deleted.

## Checkpoint File Format

```json
{
    "pipelineId": "a3f2b8c1d4e5...",
    "lastRowIndex": 5000,
    "timestamp": 1700000000,
    "stageName": "Simsoft\\DataFlow\\CallableProcessor"
}
```

## Parameters

```php
->withCheckpoint(
    path: '/tmp/my-pipeline.json',  // file path for checkpoint
    interval: 100,                   // write every N rows (default: 100)
)
```

## Pipeline ID Mismatch

If the pipeline configuration changes (stages added/removed/renamed), the
checkpoint's pipeline ID won't match. The pipeline logs a warning and starts
from the beginning.

## Example: Resumable ETL

```php
use Simsoft\DataFlow\DataFlow;

$checkpointPath = '/var/data/etl-checkpoint.json';

$result = (new DataFlow())
    ->from(new CsvExtractor('/data/large-file.csv'))
    ->withCheckpoint($checkpointPath, interval: 5000)
    ->resume()
    ->withLogger($logger)
    ->transform(fn($row) => enrichRow($row))
    ->load(fn($row) => insertToDatabase($row))
    ->run();

echo "Processed: {$result->getProcessedRows()} rows\n";
// Checkpoint file is deleted on success
```

If the process crashes at row 50,000, restarting the same script will skip the
first 50,000 rows and continue from row 50,001.
