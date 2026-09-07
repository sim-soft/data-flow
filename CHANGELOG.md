# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed

- **An extractor that failed after yielding rows was reported as a clean run.**
  A generator raises a mid-stream failure from `next()`, once, and is finished
  afterwards — `valid()` then returns `false` and the exception is gone. The
  extractor loop caught that failure while advancing and left it for "the next
  loop iteration", which never saw it. A source that died halfway through — a
  dropped database cursor, a truncated file read — produced a partial extract
  with `failedRows` at 0 and nothing in the dead letters, and even
  `ErrorStrategy::Throw` stayed silent. The failure is now carried to the loop's
  error handling, so every strategy acts on it: `Throw` propagates,
  `Skip`/`Retry`/`LogAndContinue` record it. Rows extracted before the failure
  are still delivered.

  Only sources that fail *after* yielding at least one row were affected; a
  source that failed before its first row always reported correctly.

### Added

- Tests covering the `StageRunner` retry paths: the fallback to three attempts
  when `ErrorStrategy::Retry` is selected without a `RetryConfig`, a retry that
  stops throwing but yields no row, exhaustion reporting the last exception
  rather than the first, and the extractor retry path — which the bug above had
  made unreachable for mid-stream failures.

## [3.1.0] - 2026-09-07

### Added

- `DataFlow::withDeadLetterLimit()` configures how many failed rows are retained
  for inspection. Pass `null` to restore the previous unbounded behaviour.
- `DeadLetterCollection::totalCount()`, `droppedCount()`, `isTruncated()`, and
  `getLimit()` distinguish how many rows failed from how many are retained.
- `tests/Integration/MemoryScalingTest.php` asserts that peak memory stays flat
  as datasets grow, so the "constant memory footprint" claim is now covered by
  the suite rather than only by documentation.

### Changed

- **Dead-letter retention is capped at 1,000 entries by default.** Every failure
  was previously retained in full — row, exception, and stack trace, roughly
  13 KB each — so memory grew without bound and could exhaust a long run using
  `ErrorStrategy::Skip`, which exists to survive bad rows. Capping keeps peak
  memory flat: 100,000 failures now cost 12.7 MB instead of roughly 1.3 GB.

  `getFailedRows()` remains exact — it now reports the true failure total rather
  than the number of retained entries, so counts and metrics are unaffected.
  `getDeadLetters()` and `getFailures()` return at most the limit; check
  `isTruncated()` when you need to know whether you are seeing all of them.

## [3.0.1] - 2026-09-07

Both fixes below were found by installing 3.0.0 from Packagist as a consumer
would. Neither was reachable from this repository's own test suite.

### Fixed

- `composer require simsoft/data-flow openspout/openspout` resolved OpenSpout 5,
  which fatals immediately — v5 removed the `Creator` factories `SpoutIO` calls.
  OpenSpout was listed only under `suggest`, which Composer does not enforce, so
  nothing constrained the version. `openspout/openspout: >=5.0` is now declared
  under `conflict`, so resolution picks a compatible v4 instead of failing at
  runtime.
- `SpoutLoader` created a 0-byte output file during a dry run. The writer was
  opened in the constructor, and opening a writer creates the file on disk
  immediately. It is now opened on first write, so a dry run leaves the
  filesystem untouched — as `docs/09-DRY_RUN.md` already claimed it did.

### Changed

- `SpoutLoader` reports an unwritable or unsupported output path when the first
  row is written rather than when the loader is constructed. Constructing a
  loader now has no filesystem side effects. The exception type is unchanged
  (`LoaderException`); only the point at which it surfaces has moved.

### Added

- A CI job that installs the lowest dependency versions allowed by
  `composer.json`, so a lower bound that is too loose fails here rather than for
  a consumer pinned to an older release.

## [3.0.0] - 2026-09-06

Major version because row keys are now preserved between stages. Pipelines that
relied on the old re-indexed keys will behave differently — most visibly,
`SpoutLoader` now writes multiple worksheets where it previously wrote one. See
[UPGRADING.md](UPGRADING.md).

### Fixed

- Row keys are now preserved end-to-end through the pipeline. Transform and load
  stages previously received re-indexed integer keys, which silently broke
  `SpoutLoader`'s multi-sheet feature (all keyed groups landed in one worksheet)
  and any closure relying on the `$key` argument.
- `SpoutIO` referenced `WriterMultiSheetsAbstract`, renamed to
  `AbstractWriterMultiSheets` in OpenSpout 4. The `instanceof` check was always
  false, making every `sheet()` call a silent no-op. Multi-sheet writing now
  works as documented, and the writer's default sheet is reused rather than
  orphaned, so no stray empty "Sheet1" is emitted.
- `SpoutLoader` and `SpreadsheetLoader` split the output path on the first `.`
  instead of the extension, so a dot anywhere in the path — `releases/v1.2/`,
  `C:\Users\jane.doe\` — produced a truncated filename. Both now use
  `pathinfo()` via the shared `ResolvesOutputPath` trait.
- `SpreadsheetLoader` ignored dry-run mode and wrote its file anyway. It now
  honours `isDryRun()`, matching `SpoutLoader` and the documented behaviour.
- Resuming from a checkpoint re-executed the load stage for rows already
  processed. Skipping now happens before the final stage rather than inside it,
  so `resume()` no longer re-loads completed rows and the reported processed-row
  count matches the rows actually loaded.

### Added

- `docs/00-GETTING_STARTED.md` — a runnable quick-start covering installation,
  a first pipeline, inspecting one with `preview()`, and the closure arguments.
- GitHub Actions CI running the test suite on PHP 8.3 and 8.4, plus PHPStan and
  PHPMD.
- `ResolvesOutputPath` trait, shared by both spreadsheet loaders.

### Changed

- `.gitattributes` now marks `tests/`, `docs/`, and tooling config
  `export-ignore`, so `composer require` no longer downloads them.
- Documentation corrections: fixed a PHP parse error in the quick-start example,
  corrected `preview()` and macro output samples, documented the checkpoint
  format's `version` field and what resume does not skip, corrected the metrics
  exporter callback signature (`Throwable $error`, not `string`), and added the
  missing spreadsheet processors to the processor list.

## [2.0.3] - 2026-06-15

### Changed

- Removed Composer dependencies that conflicted with a consuming project's own
  requirements.

## [2.0.2] - 2026-05-24

### Changed

- Replaced the obsolete `simsoft/db` dependency with `simsoft/fliq`.
- Reworked the tutorials site and fixed implementations referenced by the docs
  but missing from the source.

## [2.0.1] - 2026-05-18

### Fixed

- GitHub Pages documentation site: sidebar navigation and styling.

## [2.0.0] - 2026-05-18

### Added

- Per-stage error handling strategies: Throw (default), Skip, Retry,
  LogAndContinue
- `withErrorStrategy()`, `withRetry()`, `withName()` fluent methods on all
  processors
- `RetryConfig` value object for retry configuration (maxAttempts, delay)
- `DeadLetterCollection` and `DeadLetterEntry` for failed row tracking
- `PipelineResult` returned from `run()` with timing, row counts, stage metrics,
  and failures
- `StageRunner` for per-row error isolation with configurable strategies
- `PipelineExecutor` orchestrating stage execution with metrics collection
- PSR-3 logger integration via `withLogger()`
- Global error callback via `onError()`
- Progress tracking via `onProgress()` with configurable interval
- Dry-run mode via `dryRun()` — loaders skip writes when `isDryRun()` is true
- `NullLogger` as default (no-op PSR-3 logger)
- Per-stage metrics (`StageMetrics`) with timing and row counts
- Per-stage circuit breaker via `Processor::withCircuitBreaker()` — trips after a
  configurable consecutive-failure threshold, with a cooldown
  (`CircuitBreakerConfig`)
- Checkpoint and resume via `withCheckpoint()` and `resume()`, with crash-safe
  atomic checkpoint writes (`CheckpointManager`, `CheckpointData`)
- Schema validation via `validate()` with 10 built-in rules (required, string,
  int, float, email, min, max, between, in, regex) and `ClosureRule` for custom
  checks, behind the `ValidationRule` interface
- Metrics exporters behind the `MetricsExporter` interface: `LogMetricsExporter`,
  `CallbackMetricsExporter`, and `NullMetricsExporter` (the default)
- Comprehensive property-based test suite (18 properties)
- New documentation: Error Handling, Observability, Dry-Run Mode, Schema
  Validation, Circuit Breaker, Checkpoint & Resume, and Metrics Exporter
  tutorials

### Changed

- `DataFlow::run()` now returns `PipelineResult` instead of `void` (breaking
  change — callers ignoring the return value are unaffected)
- Migrated from vendored Box\Spout to `openspout/openspout` ^4.0
- SpoutExtractor, SpoutLoader, SpoutIO now use OpenSpout namespaces
- Removed vendored `simsoft/box/spout` directory

### Removed

- Vendored Box\Spout library (`simsoft/box/spout` directory)
- Box\Spout PSR-4 autoload entry from composer.json
