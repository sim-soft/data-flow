# Requirements Document

## Introduction

Enterprise resilience enhancements for the simsoft/data-flow ETL pipeline
library. This feature set introduces five production-grade capabilities:
exponential backoff with jitter for retry logic, a circuit breaker pattern for
fast-failing consistently broken stages, checkpoint/resume for crash recovery,
declarative schema validation for row data, and a metrics export interface for
real-time observability. All features are opt-in, impose zero overhead when
disabled (null object pattern), require no new Composer dependencies, and
preserve full backward compatibility with the existing fluent API and Flowable
contract.

## Glossary

- **Pipeline**: A configured DataFlow instance comprising one or more stages (
  extractors, transformers, loaders) that processes rows from source to
  destination.
- **Stage**: A single Processor within the Pipeline (an extractor, transformer,
  or loader).
- **Row**: A single data item (typically an associative array) flowing through
  the Pipeline.
- **Retry_Config**: An immutable value object holding retry parameters: maximum
  attempts, base delay, exponential flag, and maximum delay cap.
- **Exponential_Backoff**: A retry delay strategy where the wait time doubles
  with each attempt: base_delay × 2^attempt.
- **Jitter**: A random variation (±25%) applied to the computed retry delay to
  prevent thundering herd synchronization across concurrent pipelines.
- **Delay_Cap**: The maximum allowed retry delay in milliseconds, preventing
  unbounded wait times during exponential backoff.
- **Circuit_Breaker**: A resilience pattern that tracks consecutive failures per
  Stage and transitions through Closed, Open, and Half_Open states to prevent
  repeated invocations of a failing Stage.
- **Circuit_State**: One of three states: Closed (normal operation), Open (
  failing fast, skipping rows), or Half_Open (testing recovery with a single
  probe row).
- **Failure_Threshold**: The number of consecutive failures required to open a
  Circuit_Breaker.
- **Cooldown_Period**: The time in milliseconds a Circuit_Breaker remains in the
  Open state before transitioning to Half_Open.
- **Probe_Row**: The single Row allowed through during the Half_Open state to
  test whether the Stage has recovered.
- **Checkpoint**: A JSON file storing Pipeline progress (last processed row
  index, pipeline ID, timestamp, stage name) for crash recovery.
- **Checkpoint_Interval**: The number of Rows between checkpoint file writes (
  default: 100).
- **Pipeline_ID**: A unique identifier for a Pipeline execution, used to
  associate checkpoints with specific pipeline configurations.
- **Schema**: An associative array defining validation rules for Row fields,
  used by the Schema_Validator.
- **Schema_Validator**: A built-in validation stage that checks each Row against
  a declared Schema before further processing.
- **Validation_Rule**: A single rule applied to a field value (e.g., required,
  string, int, email, min, max, between, in, regex).
- **Metrics_Exporter**: An interface defining methods for recording pipeline
  metrics events in real-time during execution.
- **Log_Metrics_Exporter**: A Metrics_Exporter implementation that writes
  metrics as structured log entries via a PSR-3 logger.
- **Callback_Metrics_Exporter**: A Metrics_Exporter implementation that invokes
  user-supplied closures for each metric event.
- **Null_Metrics_Exporter**: A no-op Metrics_Exporter implementation used when
  no exporter is configured, ensuring zero overhead.
- **Pipeline_Result**: An object returned from Pipeline execution containing
  metrics about the run.
- **Error_Strategy**: A per-stage configuration that determines how the Pipeline
  responds to a row-level exception.
- **Dead_Letter_Collection**: A collection of rows that failed processing,
  stored with metadata about the failure cause.

## Requirements

### Requirement 1: Exponential Backoff Retry Delay

**User Story:** As a pipeline developer, I want retry delays to increase
exponentially between attempts, so that transient failures (rate limits, network
congestion) have time to recover without overwhelming the target system.

#### Acceptance Criteria

1. WHEN the retry Error_Strategy is active and exponential mode is enabled, THE
   Retry_Config SHALL compute the delay for each attempt as: base_delay × 2^(
   attempt_number - 1) milliseconds.
2. THE Retry_Config SHALL enable exponential backoff by default when constructed
   via the withRetry() fluent method.
3. WHEN exponential mode is disabled via the exponential parameter set to false,
   THE Retry_Config SHALL use a fixed (linear) delay equal to the base_delay for
   every attempt.
4. THE Retry_Config SHALL accept an exponential parameter (boolean, default
   true) in the withRetry() method signature: withRetry(maxAttempts: 5, delay:
   100, exponential: false).
5. WHEN the computed exponential delay exceeds the configured Delay_Cap, THE
   Retry_Config SHALL clamp the delay to the Delay_Cap value.
6. THE Retry_Config SHALL default the Delay_Cap to 30000 milliseconds when no
   explicit cap is provided.
7. THE Retry_Config SHALL accept a maxDelay parameter (integer, minimum 1) to
   configure the Delay_Cap: withRetry(maxAttempts: 5, delay: 100, maxDelay:
   60000).

### Requirement 2: Retry Jitter

**User Story:** As a pipeline developer, I want random jitter added to retry
delays, so that multiple concurrent pipelines retrying the same service do not
synchronize their retry attempts (thundering herd prevention).

#### Acceptance Criteria

1. WHEN exponential backoff is active, THE Pipeline SHALL apply ±25% random
   Jitter to the computed delay before waiting.
2. THE Jitter SHALL produce a final delay within the
   range [computed_delay × 0.75, computed_delay × 1.25].
3. WHEN the jittered delay exceeds the Delay_Cap, THE Pipeline SHALL clamp the
   final delay to the Delay_Cap.
4. WHEN linear retry mode is active (exponential: false), THE Pipeline SHALL NOT
   apply Jitter to the delay.
5. THE Jitter SHALL use a uniform random distribution within the ±25% range.

### Requirement 3: Circuit Breaker State Machine

**User Story:** As a pipeline developer, I want a circuit breaker that stops
invoking a consistently failing stage, so that the pipeline fails fast instead
of wasting time on a broken dependency.

#### Acceptance Criteria

1. THE Circuit_Breaker SHALL maintain three states: Closed, Open, and Half_Open.
2. WHILE the Circuit_Breaker is in the Closed state, THE Pipeline SHALL process
   all Rows through the Stage normally.
3. WHEN the number of consecutive failures in a Stage reaches the configured
   Failure_Threshold, THE Circuit_Breaker SHALL transition from Closed to Open.
4. WHILE the Circuit_Breaker is in the Open state, THE Pipeline SHALL skip all
   Rows for that Stage without invoking the Stage processor.
5. WHEN the Cooldown_Period elapses after the Circuit_Breaker entered the Open
   state, THE Circuit_Breaker SHALL transition from Open to Half_Open.
6. WHILE the Circuit_Breaker is in the Half_Open state, THE Pipeline SHALL allow
   exactly one Probe_Row through to the Stage.
7. WHEN the Probe_Row succeeds in the Half_Open state, THE Circuit_Breaker SHALL
   transition from Half_Open to Closed and reset the failure counter.
8. WHEN the Probe_Row fails in the Half_Open state, THE Circuit_Breaker SHALL
   transition from Half_Open to Open and restart the Cooldown_Period.
9. WHEN a Row succeeds in the Closed state, THE Circuit_Breaker SHALL reset the
   consecutive failure counter to zero.

### Requirement 4: Circuit Breaker Configuration

**User Story:** As a pipeline developer, I want to configure circuit breaker
parameters per stage, so that I can tune failure tolerance and recovery timing
for each dependency.

#### Acceptance Criteria

1. THE Processor SHALL accept circuit breaker configuration via a fluent method:
   withCircuitBreaker(failureThreshold: 5, cooldownMs: 10000).
2. THE Failure_Threshold SHALL default to 5 consecutive failures when not
   explicitly specified.
3. THE Cooldown_Period SHALL default to 10000 milliseconds when not explicitly
   specified.
4. WHEN no Circuit_Breaker is configured for a Stage, THE Pipeline SHALL process
   all Rows without circuit breaker logic (zero overhead).
5. THE Pipeline_Result SHALL include the final Circuit_State for each Stage that
   has a Circuit_Breaker configured.
6. WHEN the Circuit_Breaker is in the Open state and a Row is skipped, THE
   Pipeline SHALL record the skipped Row in the Dead_Letter_Collection with a
   circuit-open indication.

### Requirement 5: Checkpoint File Writing

**User Story:** As a pipeline developer, I want the pipeline to periodically
save progress to a JSON file, so that I can resume processing after a crash
without reprocessing all rows.

#### Acceptance Criteria

1. THE DataFlow SHALL accept a checkpoint file path via a fluent method:
   withCheckpoint('/path/to/checkpoint.json').
2. WHEN a Checkpoint is configured, THE Pipeline SHALL write the checkpoint file
   every Checkpoint_Interval rows (default: 100).
3. THE Checkpoint file SHALL contain a JSON object with keys: pipelineId (
   string), lastRowIndex (integer), timestamp (ISO 8601 string), and stageName (
   string).
4. THE Pipeline SHALL generate a deterministic Pipeline_ID based on the pipeline
   stage configuration, so that the same pipeline configuration produces the
   same ID across runs.
5. WHEN the Checkpoint_Interval is configurable via a second parameter:
   withCheckpoint('/path/to/checkpoint.json', interval: 50).
6. THE Checkpoint file write SHALL be atomic (write to a temporary file, then
   rename) to prevent corruption from mid-write crashes.
7. WHEN no Checkpoint is configured, THE Pipeline SHALL NOT perform any
   checkpoint-related I/O (zero overhead).

### Requirement 6: Checkpoint Resume

**User Story:** As a pipeline developer, I want to resume a pipeline from the
last checkpoint after a crash, so that already-processed rows are skipped and
processing continues from where it left off.

#### Acceptance Criteria

1. THE DataFlow SHALL provide a resume() fluent method that enables
   checkpoint-based resumption.
2. WHEN resume() is called and a valid Checkpoint file exists at the configured
   path, THE Pipeline SHALL skip Rows up to and including the lastRowIndex
   stored in the Checkpoint.
3. WHEN resume() is called and no Checkpoint file exists, THE Pipeline SHALL
   start processing from the beginning (row index 0).
4. WHEN resume() is called and the Checkpoint file contains a different
   Pipeline_ID than the current pipeline, THE Pipeline SHALL start processing
   from the beginning and log a warning.
5. WHEN the Pipeline completes successfully, THE Pipeline SHALL delete the
   Checkpoint file.
6. THE Checkpoint resume logic SHALL impose zero overhead on rows after the skip
   point (no per-row index comparison once resumed).

### Requirement 7: Schema Validation Stage

**User Story:** As a pipeline developer, I want to declaratively validate row
data against a schema before processing, so that invalid data is caught early
and handled by the configured error strategy.

#### Acceptance Criteria

1. THE DataFlow SHALL accept a validation schema via a fluent method: validate($
   schema).
2. THE validate() method SHALL insert a validation Stage into the pipeline at
   the point of invocation.
3. THE Schema SHALL be an associative array mapping field names to rule strings
   or closures: ['email' => 'required|email', 'age' => 'required|int|min:0'].
4. THE Schema_Validator SHALL support the following built-in Validation_Rules:
   required, string, int, float, email, min, max, between, in, regex.
5. WHEN a field value fails validation, THE Schema_Validator SHALL throw an
   exception containing the field name and the Validation_Rule that failed.
6. THE Schema_Validator SHALL handle validation failures using the configured
   Error_Strategy for the validation Stage.
7. WHEN a closure is provided as a rule, THE Schema_Validator SHALL invoke the
   closure with the field value and treat a false return as a validation
   failure.

### Requirement 8: Schema Validation Rule Semantics

**User Story:** As a pipeline developer, I want clear and predictable validation
rule behavior, so that I can trust the schema to catch invalid data
consistently.

#### Acceptance Criteria

1. WHEN the required rule is applied, THE Schema_Validator SHALL fail the field
   if the value is null, an empty string, or the field key is absent from the
   Row.
2. WHEN the string rule is applied, THE Schema_Validator SHALL fail the field if
   the value is not of type string.
3. WHEN the int rule is applied, THE Schema_Validator SHALL fail the field if
   the value is not of type integer.
4. WHEN the float rule is applied, THE Schema_Validator SHALL fail the field if
   the value is not of type float or integer.
5. WHEN the email rule is applied, THE Schema_Validator SHALL fail the field if
   the value does not pass PHP filter_var FILTER_VALIDATE_EMAIL.
6. WHEN the min:N rule is applied, THE Schema_Validator SHALL fail the field if
   the numeric value is less than N.
7. WHEN the max:N rule is applied, THE Schema_Validator SHALL fail the field if
   the numeric value is greater than N.
8. WHEN the between:M,N rule is applied, THE Schema_Validator SHALL fail the
   field if the numeric value is less than M or greater than N.
9. WHEN the in:a,b,c rule is applied, THE Schema_Validator SHALL fail the field
   if the value is not in the specified list.
10. WHEN the regex:/pattern/ rule is applied, THE Schema_Validator SHALL fail
    the field if the value does not match the regular expression pattern.
11. WHEN a field is not marked as required and the value is null or absent, THE
    Schema_Validator SHALL skip all other rules for that field.

### Requirement 9: Metrics Exporter Interface

**User Story:** As a pipeline developer, I want a metrics export interface, so
that I can integrate pipeline observability with any monitoring system (
Prometheus, StatsD, OpenTelemetry) via a simple adapter.

#### Acceptance Criteria

1. THE Metrics_Exporter interface SHALL define the following methods:
   recordRowProcessed(string $stageName), recordRowFailed(string $stageName,
   string $errorMessage), recordStageDuration(string $stageName,
   float $durationMs), and recordPipelineComplete(float $totalDurationMs,
   int $processedRows, int $failedRows).
2. THE DataFlow SHALL accept a Metrics_Exporter via a fluent method:
   withMetricsExporter($exporter).
3. WHEN a Metrics_Exporter is configured, THE Pipeline SHALL invoke
   recordRowProcessed() for each Row that successfully passes through a Stage.
4. WHEN a Metrics_Exporter is configured, THE Pipeline SHALL invoke
   recordRowFailed() for each Row that fails in a Stage.
5. WHEN a Metrics_Exporter is configured, THE Pipeline SHALL invoke
   recordStageDuration() when each Stage completes processing.
6. WHEN a Metrics_Exporter is configured, THE Pipeline SHALL invoke
   recordPipelineComplete() when the Pipeline finishes execution.
7. WHEN no Metrics_Exporter is configured, THE Pipeline SHALL use a
   Null_Metrics_Exporter that performs no operations (zero overhead).

### Requirement 10: Log Metrics Exporter

**User Story:** As a pipeline developer, I want a built-in metrics exporter that
writes structured log entries, so that I can observe pipeline metrics using my
existing PSR-3 logging infrastructure without additional dependencies.

#### Acceptance Criteria

1. THE Log_Metrics_Exporter SHALL implement the Metrics_Exporter interface.
2. THE Log_Metrics_Exporter SHALL accept a PSR-3 LoggerInterface in its
   constructor.
3. WHEN recordRowProcessed() is invoked, THE Log_Metrics_Exporter SHALL log an
   info-level message containing the stage name and a row-processed indicator.
4. WHEN recordRowFailed() is invoked, THE Log_Metrics_Exporter SHALL log a
   warning-level message containing the stage name and the error message.
5. WHEN recordStageDuration() is invoked, THE Log_Metrics_Exporter SHALL log an
   info-level message containing the stage name and the duration in
   milliseconds.
6. WHEN recordPipelineComplete() is invoked, THE Log_Metrics_Exporter SHALL log
   an info-level message containing the total duration, processed row count, and
   failed row count.

### Requirement 11: Callback Metrics Exporter

**User Story:** As a pipeline developer, I want a callback-based metrics
exporter, so that I can hook into metric events with custom logic (push to
Prometheus, emit StatsD counters) without implementing the full interface.

#### Acceptance Criteria

1. THE Callback_Metrics_Exporter SHALL implement the Metrics_Exporter interface.
2. THE Callback_Metrics_Exporter SHALL accept optional closures for each metric
   event in its constructor: onRowProcessed, onRowFailed, onStageDuration,
   onPipelineComplete.
3. WHEN a metric event occurs and the corresponding closure is configured, THE
   Callback_Metrics_Exporter SHALL invoke the closure with the event parameters.
4. WHEN a metric event occurs and no corresponding closure is configured, THE
   Callback_Metrics_Exporter SHALL perform no operation for that event.

### Requirement 12: Real-Time Metrics Emission

**User Story:** As a pipeline developer, I want metrics emitted in real-time
during pipeline execution, so that I can monitor pipeline health as it runs
rather than only after completion.

#### Acceptance Criteria

1. THE Pipeline SHALL invoke Metrics_Exporter methods during execution as each
   event occurs, not batched at the end.
2. WHEN a Row is processed by a Stage, THE Pipeline SHALL invoke
   recordRowProcessed() immediately after the Row exits the Stage.
3. WHEN a Row fails in a Stage, THE Pipeline SHALL invoke recordRowFailed()
   immediately after the failure is handled.
4. WHEN a Stage completes all Row processing, THE Pipeline SHALL invoke
   recordStageDuration() immediately after the Stage finishes.
