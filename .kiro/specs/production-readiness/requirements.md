# Requirements Document

## Introduction

Production-readiness enhancements for the simsoft/data-flow ETL pipeline
library. This feature set introduces robust error handling with configurable
strategies, PSR-3 logging integration, pipeline metrics and observability, and
migration from the vendored Box\Spout library to the actively maintained
openspout/openspout package. Together these capabilities make the library
suitable for production workloads where reliability, visibility, and
maintainability are critical.

## Glossary

- **Pipeline**: A configured DataFlow instance comprising one or more stages (
  extractors, transformers, loaders) that processes rows from source to
  destination.
- **Stage**: A single Processor within the Pipeline (an extractor, transformer,
  or loader).
- **Row**: A single data item (associative array) flowing through the Pipeline.
- **Error_Strategy**: A per-stage configuration that determines how the Pipeline
  responds to a row-level exception: throw, skip, retry, or log-and-continue.
- **Dead_Letter_Collection**: A collection of rows that failed processing,
  stored with metadata about the failure cause and originating stage.
- **Pipeline_Result**: An object returned from Pipeline execution containing
  metrics about the run (row counts, timing, memory usage).
- **Progress_Callback**: A user-supplied callable invoked at configurable
  intervals during Pipeline execution to report progress.
- **Dry_Run_Mode**: A Pipeline execution mode where loaders receive rows but
  skip actual write operations.
- **Logger**: A PSR-3 LoggerInterface implementation injected into the Pipeline
  for structured logging.
- **Null_Logger**: A PSR-3 logger implementation that discards all log messages,
  used as the default when no Logger is configured.
- **Backoff_Delay**: The time in milliseconds between retry attempts, applied
  when the retry Error_Strategy is active.
- **OpenSpout**: The actively maintained fork of Box\Spout (openspout/openspout)
  providing streaming spreadsheet I/O.
- **SpoutExtractor**: The extractor stage that reads spreadsheet files using the
  Spout library.
- **SpoutLoader**: The loader stage that writes spreadsheet files using the
  Spout library.
- **SpoutIO**: The abstraction layer wrapping Spout reader/writer operations.

## Requirements

### Requirement 1: Configurable Error Strategy Per Stage

**User Story:** As a pipeline developer, I want to configure how each stage
handles row-level errors, so that I can control failure behavior without
modifying stage logic.

#### Acceptance Criteria

1. THE Pipeline SHALL support four Error_Strategy values: throw, skip, retry,
   and log-and-continue.
2. WHEN no Error_Strategy is configured for a Stage, THE Pipeline SHALL use the
   throw strategy as the default.
3. WHEN the throw strategy is active and a Stage throws an exception for a Row,
   THE Pipeline SHALL propagate the exception immediately.
4. WHEN the skip strategy is active and a Stage throws an exception for a Row,
   THE Pipeline SHALL discard the Row and continue processing the next Row.
5. WHEN the retry strategy is active and a Stage throws an exception for a Row,
   THE Pipeline SHALL re-attempt processing the Row up to the configured maximum
   number of attempts.
6. WHEN the log-and-continue strategy is active and a Stage throws an exception
   for a Row, THE Pipeline SHALL log the error and continue processing the next
   Row with the original Row data intact.
7. THE Pipeline SHALL allow Error_Strategy configuration via a fluent method on
   individual Processor instances.

### Requirement 2: Global Error Callback

**User Story:** As a pipeline developer, I want to register a global error
handler on the DataFlow, so that I can centralize error reporting and alerting
logic.

#### Acceptance Criteria

1. THE DataFlow SHALL accept an onError callback via a fluent method.
2. WHEN a Stage encounters an exception and the Error_Strategy does not
   propagate the exception, THE DataFlow SHALL invoke the onError callback with
   the exception, the failing Row, and the Stage name.
3. WHEN no onError callback is registered, THE DataFlow SHALL proceed with the
   configured Error_Strategy without invoking any callback.

### Requirement 3: Retry with Configurable Attempts and Backoff

**User Story:** As a pipeline developer, I want to configure retry attempts and
backoff delay for extractors and loaders, so that transient failures (network
timeouts, rate limits) are handled gracefully.

#### Acceptance Criteria

1. WHEN the retry Error_Strategy is configured, THE Pipeline SHALL accept a
   maximum attempt count (integer, minimum 1).
2. WHEN the retry Error_Strategy is configured, THE Pipeline SHALL accept a
   Backoff_Delay in milliseconds (integer, minimum 0).
3. WHEN a retry attempt fails, THE Pipeline SHALL wait for the configured
   Backoff_Delay before the next attempt.
4. WHEN all retry attempts are exhausted for a Row, THE Pipeline SHALL add the
   Row to the Dead_Letter_Collection and continue processing the next Row.
5. THE Pipeline SHALL default to 3 retry attempts and 100 milliseconds
   Backoff_Delay when retry is configured without explicit values.

### Requirement 4: Partial Failure Reporting

**User Story:** As a pipeline developer, I want to track which rows failed and
why without stopping the pipeline, so that I can investigate failures after the
run completes.

#### Acceptance Criteria

1. WHEN a Row fails processing in any Stage (under skip, retry-exhausted, or
   log-and-continue strategies), THE Pipeline SHALL record the failure with the
   Row data, Stage name, exception message, and row index.
2. THE Pipeline_Result SHALL include the total count of failed Rows.
3. THE Pipeline_Result SHALL include the total count of skipped Rows.
4. THE Pipeline_Result SHALL provide access to the list of recorded failures.

### Requirement 5: Dead-Letter Collection

**User Story:** As a pipeline developer, I want failed rows collected into a
dead-letter collection, so that I can inspect and reprocess them later.

#### Acceptance Criteria

1. THE Pipeline SHALL maintain a Dead_Letter_Collection during execution.
2. WHEN a Row fails processing (under skip, retry-exhausted, or log-and-continue
   strategies), THE Pipeline SHALL add the Row to the Dead_Letter_Collection
   with the exception, Stage name, and row index.
3. THE Pipeline_Result SHALL provide access to the Dead_Letter_Collection.
4. THE Dead_Letter_Collection SHALL be iterable and countable.
5. WHEN the throw Error_Strategy is active, THE Pipeline SHALL NOT add Rows to
   the Dead_Letter_Collection (the exception propagates immediately).

### Requirement 6: PSR-3 Logger Injection

**User Story:** As a pipeline developer, I want to inject a PSR-3 logger into
the DataFlow, so that pipeline operations are logged using my application's
existing logging infrastructure.

#### Acceptance Criteria

1. THE DataFlow SHALL accept a PSR-3 LoggerInterface instance via a fluent
   method.
2. WHEN no Logger is configured, THE DataFlow SHALL use a Null_Logger that
   discards all messages.
3. THE Null_Logger SHALL impose zero measurable overhead on Pipeline execution
   when no Logger is configured.

### Requirement 7: Automatic Stage Boundary Logging

**User Story:** As a pipeline developer, I want automatic logging at stage
boundaries, so that I can trace pipeline execution without adding manual log
calls.

#### Acceptance Criteria

1. WHEN a Stage begins processing, THE Pipeline SHALL log a debug-level message
   containing the Stage name.
2. WHEN a Stage completes processing, THE Pipeline SHALL log an info-level
   message containing the Stage name and the number of Rows processed by that
   Stage.
3. WHEN a Stage encounters an exception, THE Pipeline SHALL log an error-level
   message containing the Stage name, the exception message, and the row index.

### Requirement 8: Row-Level Failure Logging

**User Story:** As a pipeline developer, I want row-level logging for failures,
so that I can identify exactly which row failed, in which stage, and what error
occurred.

#### Acceptance Criteria

1. WHEN a Row fails processing in a Stage, THE Pipeline SHALL log a
   warning-level message containing the row index, Stage name, and exception
   message.
2. THE log message SHALL include sufficient context to identify the failing Row
   without logging the entire Row data at warning level.
3. WHEN debug-level logging is enabled, THE Pipeline SHALL include the Row data
   in the log context array.

### Requirement 9: Pipeline Run Metadata

**User Story:** As a pipeline developer, I want pipeline run metadata (timing,
row counts), so that I can monitor pipeline performance and health.

#### Acceptance Criteria

1. THE Pipeline_Result SHALL include the Pipeline start time as a
   DateTimeImmutable.
2. THE Pipeline_Result SHALL include the Pipeline end time as a
   DateTimeImmutable.
3. THE Pipeline_Result SHALL include the total number of Rows processed (passed
   through all stages successfully).
4. THE Pipeline_Result SHALL include the total number of Rows that failed.
5. THE Pipeline_Result SHALL include the total number of Rows that were skipped.
6. THE Pipeline_Result SHALL include the total execution duration in
   milliseconds.
7. THE Pipeline_Result SHALL include the peak memory usage in bytes during the
   Pipeline run.

### Requirement 10: Pipeline Result Object

**User Story:** As a pipeline developer, I want the run() method to return a
result object with metrics, so that I can programmatically inspect pipeline
outcomes.

#### Acceptance Criteria

1. THE DataFlow run() method SHALL return a Pipeline_Result object instead of
   void.
2. THE Pipeline_Result SHALL provide per-stage timing (duration in milliseconds
   for each Stage).
3. THE Pipeline_Result SHALL provide per-stage row counts (number of Rows that
   entered each Stage).
4. THE Pipeline_Result SHALL be serializable to an associative array via a
   toArray() method.

### Requirement 11: Progress Callback Support

**User Story:** As a pipeline developer, I want to receive progress
notifications during pipeline execution, so that I can display progress bars or
emit monitoring events.

#### Acceptance Criteria

1. THE DataFlow SHALL accept a Progress_Callback via a fluent method.
2. THE DataFlow SHALL accept a configurable interval (integer N) specifying how
   often the Progress_Callback is invoked (every N Rows).
3. WHEN the configured interval of Rows have been processed, THE Pipeline SHALL
   invoke the Progress_Callback with the current row count and the total elapsed
   time in milliseconds.
4. WHEN no Progress_Callback is configured, THE Pipeline SHALL NOT perform any
   progress tracking overhead.
5. THE Progress_Callback interval SHALL default to 100 Rows when a callback is
   registered without an explicit interval.

### Requirement 12: Dry-Run Mode

**User Story:** As a pipeline developer, I want a dry-run mode that executes the
pipeline but skips actual writes, so that I can validate pipeline logic without
side effects.

#### Acceptance Criteria

1. THE DataFlow SHALL accept a dry-run flag via a fluent method.
2. WHILE Dry_Run_Mode is active, THE Pipeline SHALL execute all extractors and
   transformers normally.
3. WHILE Dry_Run_Mode is active, THE Pipeline SHALL invoke loaders but skip
   actual write operations (file writes, database inserts, API calls).
4. WHILE Dry_Run_Mode is active, THE Pipeline_Result SHALL indicate that the run
   was a dry run.
5. THE Pipeline_Result row counts SHALL reflect Rows that would have been
   written during a dry run.

### Requirement 13: Migrate to OpenSpout Namespace

**User Story:** As a library maintainer, I want to replace the vendored
Box\Spout with openspout/openspout, so that the library uses an actively
maintained dependency without PHP deprecation warnings.

#### Acceptance Criteria

1. THE SpoutExtractor SHALL use OpenSpout namespace classes instead of Box\Spout
   namespace classes.
2. THE SpoutLoader SHALL use OpenSpout namespace classes instead of Box\Spout
   namespace classes.
3. THE SpoutIO SHALL use OpenSpout namespace classes instead of Box\Spout
   namespace classes.
4. THE composer.json SHALL require openspout/openspout as a dependency.
5. THE composer.json SHALL remove the Box\Spout PSR-4 autoload entry.
6. WHEN the migration is complete, THE library SHALL produce zero PHP
   deprecation warnings from the Spout integration.

### Requirement 14: Remove Vendored Spout Directory

**User Story:** As a library maintainer, I want to remove the vendored
simsoft/box/spout directory, so that the codebase does not contain unmaintained
duplicated code.

#### Acceptance Criteria

1. WHEN the OpenSpout migration is complete, THE repository SHALL NOT contain
   the simsoft/box/spout directory.
2. THE autoload configuration SHALL NOT reference the simsoft/box/spout path.
3. WHEN the vendored directory is removed, THE library SHALL pass all existing
   tests without modification to test assertions.
