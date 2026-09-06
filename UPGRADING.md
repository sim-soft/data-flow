# Upgrading Guide

## From 3.0.0 to 3.0.1

No API changes. One thing to check if you wrap loader construction in a
`try`/`catch`.

### `SpoutLoader` reports a bad output path later

`SpoutLoader` used to open its writer in the constructor, so an unwritable or
unsupported path threw `LoaderException` from `new SpoutLoader(...)`. The writer
is now opened on the first write, so that same exception surfaces when the
pipeline runs instead.

The exception type has not changed — only where it is thrown. If you wrote:

```php
try {
    $loader = new SpoutLoader($path);   // no longer throws
} catch (LoaderException $e) {
    // ...
}
```

move the `catch` to cover `run()`, or let the pipeline's error handling take it.

This was necessary to fix dry-run mode: opening a writer creates the file on
disk, so a dry run was leaving a 0-byte file behind.

## From 2.0.x to 3.0.0

No API changes. One behavioural fix is worth knowing about before you upgrade.

### Row keys now reach your stages intact

Keys were previously re-indexed to `0, 1, 2, …` as rows moved between stages.
They are now preserved from the extractor all the way to the loader.

This is a fix, not a new feature — but if you wrote code that worked *around* the
old behaviour, check it:

- **`SpoutLoader` multi-sheet writing now works.** Keyed rows previously all
  landed in one worksheet. If you were splitting sheets manually, you can drop
  that workaround. Expect existing pipelines to start producing multiple
  worksheets where they used to produce one.
- **Closures receiving `$key` now see the original key.** If a `load()` or
  `transform()` closure used its second argument as a running counter, it will
  now receive whatever key the source produced. Keep your own counter instead.

Pipelines whose sources yield plain lists are unaffected: `0, 1, 2, …` in,
`0, 1, 2, …` out.

### Resume no longer re-loads completed rows

`resume()` previously re-executed the load stage for rows already processed
before the crash. It now skips them. If you added idempotency to a loader purely
to tolerate that, it is no longer required — though keeping it is still wise, as
delivery remains at-least-once between checkpoint intervals. See
[Checkpoint & Resume](docs/12-CHECKPOINT_RESUME.md).

## From 1.x to 2.0

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
