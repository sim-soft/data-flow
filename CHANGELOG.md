# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
- Comprehensive property-based test suite (18 properties)
- New documentation: Error Handling, Observability, Dry-Run Mode tutorials

### Changed

- `DataFlow::run()` now returns `PipelineResult` instead of `void` (breaking
  change — callers ignoring the return value are unaffected)
- Migrated from vendored Box\Spout to `openspout/openspout` ^4.0
- SpoutExtractor, SpoutLoader, SpoutIO now use OpenSpout namespaces
- Removed vendored `simsoft/box/spout` directory

### Removed

- Vendored Box\Spout library (`simsoft/box/spout` directory)
- Box\Spout PSR-4 autoload entry from composer.json
