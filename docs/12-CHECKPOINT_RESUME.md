
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
up to the last saved position, so the loader never sees them a second time.

### What resume does and does not skip

Skipping happens just before the **final** stage. Extract and transform stages
re-run from the beginning on every resume — only the load is skipped. In the
example above, `processRow()` runs for all million rows while `saveRow()` runs
only for the rows after the checkpoint.

This matters in two ways:

- **Transforms must be side-effect free.** If `processRow()` writes to a database
  or calls an API, those calls repeat on resume. Keep writes in the load stage.
- **Delivery is at-least-once, not exactly-once.** The checkpoint records the
  last *interval* boundary, not the last row. With `interval: 1000` and a crash
  at row 5,432, the checkpoint holds 5,000, so rows 5,001–5,432 are loaded twice.
  Use a smaller interval to narrow the window (at the cost of more file writes),
  and make the load idempotent — an upsert rather than an insert — if duplicates
  are unacceptable.

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
    "version": 1,
    "pipelineId": "a3f2b8c1d4e5...",
    "lastRowIndex": 5000,
    "timestamp": 1700000000,
    "stageName": "Simsoft\\DataFlow\\CallableProcessor"
}
```

`version` is the checkpoint format version and is required. A checkpoint file
without it — or with a version this release does not recognise — is ignored, and
the pipeline starts from the beginning rather than resuming from a format it
cannot read.

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

If the process crashes at row 50,000, restarting the same script skips loading
the first 50,000 rows and continues from row 50,001. The CSV is still read and
`enrichRow()` still runs for those rows — only `insertToDatabase()` is skipped.
