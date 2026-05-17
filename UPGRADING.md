# Upgrading Guide

## From pre-production-readiness to current

### Breaking Changes

#### `DataFlow::run()` return type changed from `void` to `PipelineResult`

Previously:

```php
(new DataFlow())->from($data)->load($loader)->run();
// run() returned void
```

Now:

```php
$result = (new DataFlow())->from($data)->load($loader)->run();
// run() returns PipelineResult
```

**Impact:** Code that called `run()` without capturing the return value
continues to work unchanged. Code that type-hinted the return value as `void`
will need updating.

#### Box\Spout replaced with OpenSpout

If you extended or referenced Box\Spout classes directly:

- Replace `Box\Spout\*` imports with `OpenSpout\*`
- Replace `ReaderEntityFactory::createReaderFromFile()` with
  `ReaderFactory::createFromFile()`
- Replace `WriterEntityFactory::createWriterFromFile()` with
  `WriterFactory::createFromFile()`
- Replace `WriterEntityFactory::createRowFromArray()` with `Row::fromValues()`

### New Features (non-breaking)

All new features are opt-in and backward compatible:

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from($source)
    ->withLogger($psrLogger)                    // optional: PSR-3 logging
    ->onError(fn($e, $row, $stage) => ...)      // optional: error callback
    ->onProgress(fn($count, $ms) => ..., 100)   // optional: progress tracking
    ->transform(
        (new MyTransformer())
            ->withErrorStrategy(ErrorStrategy::Skip)  // optional: error strategy
            ->withName('my-stage')                     // optional: stage naming
    )
    ->load($loader)
    ->dryRun()                                  // optional: dry-run mode
    ->run();

// Use the result
echo $result->getProcessedRows();
echo $result->getDurationMs();
```
