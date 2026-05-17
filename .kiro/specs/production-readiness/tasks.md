# Implementation Plan: Production Readiness

## Overview

This plan implements production-readiness enhancements for simsoft/data-flow in
incremental steps: value objects and enums first, then core error handling
infrastructure, observability (logging + metrics), execution control (result,
progress, dry-run), and finally the OpenSpout migration. Each step builds on the
previous, ensuring no orphaned code.

## Tasks

- [x]
    1. Create value objects, enums, and foundational classes

    - [x] 1.1 Create the ErrorStrategy enum
        - Create `src/Enums/ErrorStrategy.php` with string-backed enum: Throw,
          Skip, Retry, LogAndContinue
        - _Requirements: 1.1_

    - [x] 1.2 Create the RetryConfig value object
        - Create `src/RetryConfig.php` as `final readonly class` with
          `maxAttempts` (default 3, >= 1) and `backoffMs` (default 100, >= 0)
          constructor validation
        - _Requirements: 3.1, 3.2, 3.5_

    - [x] 1.3 Create the DeadLetterEntry value object
        - Create `src/DeadLetterEntry.php` as `final readonly class` with
          properties: row, stageName, rowIndex, exception, occurredAt
        - _Requirements: 5.2_

    - [x] 1.4 Create the DeadLetterCollection class
        - Create `src/DeadLetterCollection.php` implementing `\Countable` and
          `\IteratorAggregate` with add(), count(), getIterator(), toArray()
          methods
        - _Requirements: 5.1, 5.4_

    - [x] 1.5 Create the StageMetrics value object
        - Create `src/StageMetrics.php` as `final readonly class` with
          properties: stageName, rowsEntered, rowsExited, durationMs
        - _Requirements: 10.2, 10.3_

    - [x] 1.6 Create the PipelineResult class
        - Create `src/PipelineResult.php` with constructor accepting all
          metrics, getter methods, and toArray() serialization
        - _Requirements: 4.2, 4.3, 4.4, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 10.1,
          10.2, 10.3, 10.4_

    - [x] 1.7 Create the NullLogger class
        - Create `src/Logging/NullLogger.php` extending `Psr\Log\AbstractLogger`
          with no-op log() method
        - _Requirements: 6.2, 6.3_

  - [x]* 1.8 Write unit tests for value objects and enums
    - Test ErrorStrategy has exactly 4 cases
    - Test RetryConfig rejects maxAttempts < 1 and backoffMs < 0
    - Test RetryConfig defaults (3 attempts, 100ms)
    - Test DeadLetterCollection is countable and iterable
    - Test PipelineResult getters and toArray()
    - Test NullLogger implements LoggerInterface
    - _Requirements: 1.1, 3.1, 3.2, 3.5, 5.4, 10.4, 6.2_

- [x]
    2. Add error strategy configuration to Processor and Loader

    - [x] 2.1 Extend Processor with error strategy and naming support
        - Add private properties: `$errorStrategy` (default ErrorStrategy::
          Throw), `$retryConfig`, `$name`
        - Add fluent methods: `withErrorStrategy()`, `withRetry()`, `withName()`
        - Add getters: `getErrorStrategy()`, `getRetryConfig()`, `getName()`
        - _Requirements: 1.2, 1.7, 3.1, 3.2_

    - [x] 2.2 Extend Loader with dry-run support
        - Add private `$dryRun` property with `setDryRun()` and `isDryRun()`
          methods
        - _Requirements: 12.3_

  - [x]* 2.3 Write unit tests for Processor and Loader extensions
    - Test default error strategy is Throw
    - Test withErrorStrategy() returns $this and sets strategy
    - Test withRetry() sets strategy to Retry and creates RetryConfig
    - Test withName() and getName() (defaults to class name)
    - Test Loader isDryRun() defaults to false
    - _Requirements: 1.2, 1.7, 12.3_

- [x]
    3. Implement StageRunner with error handling logic

    - [x] 3.1 Create the StageRunner class
        - Create `src/StageRunner.php` with `run()` method that wraps stage
          iterator output
        - Implement throw strategy: propagate exception immediately
        - Implement skip strategy: discard failing row, record failure, continue
        - Implement log-and-continue strategy: log error, record failure,
          continue with next row
        - Implement retry strategy: re-attempt with backoff delay, exhaust
          attempts then add to dead-letter
        - Invoke onError callback for non-throw strategies
        - Log stage boundary messages (debug on start, info on complete with row
          count)
        - Log error-level on exception, warning-level with row
          index/stage/message
        - Include row data in debug-level log context only
        - _Requirements: 1.3, 1.4, 1.5, 1.6, 2.2, 3.3, 3.4, 5.2, 5.5, 7.1, 7.2,
          7.3, 8.1, 8.2, 8.3_

  - [x]* 3.2 Write property tests for error strategies (Properties 1-4)
    - **Property 1: Throw Strategy Propagates Exceptions**
    - **Property 2: Skip Strategy Excludes Failing Rows**
    - **Property 3: Retry Strategy Invokes Stage N Times**
    - **Property 4: Log-and-Continue Preserves Subsequent Row Data**
    - **Validates: Requirements 1.3, 1.4, 1.5, 1.6**

  - [x]* 3.3 Write property test for global error callback (Property 5)
    - **Property 5: Global Error Callback Receives Correct Arguments**
    - **Validates: Requirements 2.2**

  - [x]* 3.4 Write property tests for retry behavior (Properties 6-7)
    - **Property 6: Retry Backoff Delay Is Applied**
    - **Property 7: Retry Exhaustion Adds to Dead-Letter Collection**
    - **Validates: Requirements 3.3, 3.4, 5.2**

  - [x]* 3.5 Write property tests for failure records (Properties 8-9)
    - **Property 8: Failure Records Contain Complete Metadata**
    - **Property 9: Row Count Invariant**
    - **Validates: Requirements 4.1, 4.2, 4.3, 5.2, 9.3, 9.4, 9.5**

  - [x]* 3.6 Write property tests for logging (Properties 10-12)
    - **Property 10: Stage Boundary Logging**
    - **Property 11: Failure Logging at Appropriate Levels**
    - **Property 12: Row Data Appears in Debug Context Only**
    - **Validates: Requirements 7.1, 7.2, 7.3, 8.1, 8.2, 8.3**

- [x]
    4. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    5. Implement PipelineExecutor and integrate into DataFlow

    - [x] 5.1 Create the PipelineExecutor class
        - Create `src/PipelineExecutor.php` that orchestrates stage execution
          via StageRunner
        - Collect per-stage metrics (timing, row counts) into StageMetrics
          objects
        - Track progress and invoke progress callback at configured interval
        - Record start/end time, peak memory, and build PipelineResult
        - Set dry-run flag on Loader instances before execution
        - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 10.2, 10.3, 11.3,
          12.3_

    - [x] 5.2 Modify DataFlow to use PipelineExecutor
        - Add private properties: `$logger`, `$onError`, `$onProgress`,
          `$progressInterval`, `$dryRun`
        - Add fluent methods: `withLogger()`, `onError()`, `onProgress()`,
          `dryRun()`
        - Initialize NullLogger as default in constructor
        - Collect stages during from()/transform()/load() calls for executor
        - Change `run()` return type from `void` to `PipelineResult`
        - Instantiate PipelineExecutor and delegate execution
        - Validate progress interval >= 1
        - _Requirements: 2.1, 2.3, 6.1, 6.2, 10.1, 11.1, 11.2, 11.4, 11.5, 12.1,
          12.4_

  - [x]* 5.3 Write property tests for metrics and progress (Properties 13-16)
    - **Property 13: Duration Equals Time Difference**
    - **Property 14: Per-Stage Metrics Consistency**
    - **Property 15: PipelineResult Serialization Round-Trip**
    - **Property 16: Progress Callback Invocation Frequency**
    - **Validates: Requirements 9.6, 10.2, 10.3, 10.4, 11.3**

  - [x]* 5.4 Write property tests for dry-run (Properties 17-18)
    - **Property 17: Dry-Run Equivalence for Non-Loader Stages**
    - **Property 18: Dry-Run Suppresses Loader Side Effects**
    - **Validates: Requirements 12.2, 12.3, 12.5**

- [x]
    6. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    7. Migrate from Box\Spout to OpenSpout

    - [x] 7.1 Update composer.json for OpenSpout
        - Add `openspout/openspout` as a dependency
        - Remove the Box\Spout PSR-4 autoload entry from `autoload.classmap` or
          `autoload.psr-4`
        - Run `composer update` to install the new dependency
        - _Requirements: 13.4, 13.5_

    - [x] 7.2 Migrate SpoutExtractor to OpenSpout namespace
        - Replace `Box\Spout\Reader\*` imports with `OpenSpout\Reader\*`
        - Replace `ReaderEntityFactory::createReaderFromFile()` with
          `ReaderFactory::createFromFile()`
        - Update cell/row access patterns to match OpenSpout API
        - _Requirements: 13.1_

    - [x] 7.3 Migrate SpoutLoader to OpenSpout namespace
        - Replace `Box\Spout\Writer\*` imports with `OpenSpout\Writer\*`
        - Replace `WriterEntityFactory::createWriterFromFile()` with
          `WriterFactory::createFromFile()`
        - Replace `WriterEntityFactory::createRowFromArray()` with
          `Row::fromValues()`
        - Update style handling to use OpenSpout direct construction
        - _Requirements: 13.2_

    - [x] 7.4 Migrate SpoutIO to OpenSpout namespace
        - Replace all `Box\Spout\*` imports with corresponding `OpenSpout\*`
          classes
        - Update any factory method calls to match OpenSpout API
        - _Requirements: 13.3_

    - [x] 7.5 Remove vendored simsoft/box/spout directory
        - Delete the entire `simsoft/box/spout` directory
        - Verify no remaining references to `Box\Spout` namespace in source
          files
        - _Requirements: 14.1, 14.2_

  - [x]* 7.6 Write integration tests for OpenSpout migration
    - Test SpoutExtractor reads XLSX files without deprecation warnings
    - Test SpoutLoader writes XLSX files without deprecation warnings
    - Verify `simsoft/box/spout` directory does not exist
    - Verify composer.json has no Box\Spout autoload entries
    - _Requirements: 13.6, 14.1, 14.2, 14.3_

- [x]
    8. Final checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design
  document
- Unit tests validate specific examples and edge cases
- The OpenSpout migration (task 7) is independent of the error
  handling/observability work but is placed last to avoid disrupting existing
  tests during development
- The `run()` method return type change from `void` to `PipelineResult` is a
  breaking change — existing callers that ignore the return value will continue
  to work, but type-hinted code may need updates

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3", "1.5", "1.7"] },
    { "id": 1, "tasks": ["1.4", "1.6"] },
    { "id": 2, "tasks": ["1.8", "2.1", "2.2"] },
    { "id": 3, "tasks": ["2.3", "3.1"] },
    { "id": 4, "tasks": ["3.2", "3.3", "3.4", "3.5", "3.6"] },
    { "id": 5, "tasks": ["5.1"] },
    { "id": 6, "tasks": ["5.2"] },
    { "id": 7, "tasks": ["5.3", "5.4"] },
    { "id": 8, "tasks": ["7.1"] },
    { "id": 9, "tasks": ["7.2", "7.3", "7.4"] },
    { "id": 10, "tasks": ["7.5"] },
    { "id": 11, "tasks": ["7.6"] }
  ]
}
```
