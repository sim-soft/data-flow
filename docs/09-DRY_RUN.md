
# Dry-Run Mode

Dry-run mode lets you validate a pipeline end-to-end without performing actual
write operations. Extractors and transformers run normally, but loaders skip
their side effects (file writes, database inserts, API calls).

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($records)
    ->transform(fn($row) => validateAndEnrich($row))
    ->load(new DatabaseLoader())
    ->dryRun()  // enable dry-run mode
    ->run();

echo "Would process: {$result->getProcessedRows()} rows\n";
echo "Dry run: " . ($result->isDryRun() ? 'yes' : 'no') . "\n";
// No rows were actually written to the database
```

## How It Works

When dry-run is enabled:

1. **Extractors** run normally — data is read from the source.
2. **Transformers** run normally — data is validated, mapped, enriched.
3. **Loaders** receive all rows but `isDryRun()` returns `true` — loaders should
   check this flag and skip write operations.

The `PipelineResult` reflects what *would* have happened: row counts, timing,
and metrics are all captured as if the pipeline ran for real.

## Writing Dry-Run Aware Loaders

Custom loaders should check `$this->isDryRun()` before performing writes.

```php
use Simsoft\DataFlow\Loader;
use Iterator;

class ApiLoader extends Loader
{
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        foreach ($dataFrame as $row) {
            if (!$this->isDryRun()) {
                // Only perform the actual API call in non-dry-run mode
                $this->httpClient->post('/api/records', $row);
            }

            yield $row;
        }
    }
}
```

The built-in `SpoutLoader` already respects dry-run mode — it skips file writes
when `isDryRun()` is true.

## Use Cases

### Validate Data Before Import

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

// First: dry-run to check for errors
$dryResult = (new DataFlow())
    ->from(new SpoutExtractor('import.xlsx'))
    ->transform(
        (new DataValidator())->withErrorStrategy(ErrorStrategy::Skip)->withName('validator')
    )
    ->load(new DatabaseLoader())
    ->dryRun()
    ->run();

echo "Rows to import: {$dryResult->getProcessedRows()}\n";
echo "Invalid rows: {$dryResult->getFailedRows()}\n";

if ($dryResult->getFailedRows() === 0) {
    // All clear — run for real
    $realResult = (new DataFlow())
        ->from(new SpoutExtractor('import.xlsx'))
        ->transform(new DataValidator())
        ->load(new DatabaseLoader())
        ->run();

    echo "Imported: {$realResult->getProcessedRows()} rows\n";
} else {
    echo "Fix errors before importing.\n";
    foreach ($dryResult->getDeadLetters() as $entry) {
        echo "  Row {$entry->rowIndex}: {$entry->exception->getMessage()}\n";
    }
}
```

### Estimate Pipeline Duration

```php
$result = (new DataFlow())
    ->from($largeDataset)
    ->transform(new HeavyTransformer())
    ->load(new SlowApiLoader())
    ->dryRun()
    ->run();

echo "Estimated time (without writes): "
    . round($result->getDurationMs() / 1000, 1) . "s\n";
echo "Rows that would be written: {$result->getProcessedRows()}\n";
```

### Toggle Dry-Run via Configuration

```php
$isDryRun = getenv('ETL_DRY_RUN') === 'true';

$flow = (new DataFlow())
    ->from($source)
    ->transform(new Transformer())
    ->load(new Loader());

if ($isDryRun) {
    $flow->dryRun();
}

$result = $flow->run();

if ($result->isDryRun()) {
    echo "[DRY RUN] No data was written.\n";
}

echo "Rows: {$result->getProcessedRows()}\n";
```
