# Implementation Plan: Enterprise Resilience

## Overview

Implement five opt-in resilience capabilities (exponential backoff retry,
circuit breaker, checkpoint/resume, schema validation, metrics exporter) as
additive extensions to the existing DataFlow fluent API and Processor base
class. All features use null object pattern for zero overhead when disabled.

## Tasks

- [x]
    1. Core configuration value objects and enums

    - [x] 1.1 Create `src/Enums/CircuitState.php` enum with Closed, Open,
      HalfOpen cases
        - _Requirements: 3.1_
    - [x] 1.2 Create `src/CircuitBreakerConfig.php` readonly class with
      failureThreshold and cooldownMs properties
        - _Requirements: 4.1, 4.2, 4.3_
    - [x] 1.3 Enhance `src/RetryConfig.php` with exponential (bool, default
      true) and maxDelay (int, default 30000) properties, add
      `computeDelay(int $attempt): int` and `applyJitter(int $delayMs): int`
      methods
        - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 2.1, 2.2, 2.3, 2.4,
          2.5_

- [x]
    2. Retry and circuit breaker property tests
       - [x]* 2.1 Write property test for exponential delay computation with
       clamping

    - **Property 1: Exponential delay computation with clamping**
    - **Validates: Requirements 1.1, 1.5**
      - [x]* 2.2 Write property test for linear delay constancy
    - **Property 2: Linear delay is constant**
    - **Validates: Requirements 1.3**
      - [x]* 2.3 Write property test for jitter bounds invariant
    - **Property 3: Jitter bounds invariant**
    - **Validates: Requirements 2.1, 2.2, 2.3**
      - [x]* 2.4 Write property test for linear mode no jitter
    - **Property 4: Linear mode applies no jitter**
    - **Validates: Requirements 2.4**

- [x]
    3. Circuit breaker implementation

    - [x] 3.1 Create `src/CircuitBreaker.php` with state machine logic (
      Closed→Open→HalfOpen transitions), `isCallAllowed()`, `recordSuccess()`,
      `recordFailure()` methods using `hrtime(true)` for cooldown timing
        - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9_
          - [x]* 3.2 Write property test for circuit breaker state determines
          call allowance
        - **Property 5: Circuit breaker state determines call allowance**
        - **Validates: Requirements 3.2, 3.4**
          - [x]* 3.3 Write property test for failure threshold triggers Open
          state
        - **Property 6: Failure threshold triggers Open state**
        - **Validates: Requirements 3.3**
          - [x]* 3.4 Write property test for success resets failure counter
        - **Property 7: Success in Closed state resets failure counter**
        - **Validates: Requirements 3.9**
          - [x]* 3.5 Write property test for HalfOpen probe outcome
        - **Property 8: HalfOpen probe outcome determines transition**
        - **Validates: Requirements 3.7, 3.8**

- [x]
    4. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    5. Checkpoint/Resume implementation

    - [x] 5.1 Create `src/CheckpointData.php` readonly class with pipelineId,
      lastRowIndex, timestamp, stageName properties, `fromJson(string): ?self`
      and `toJson(): string` methods
        - _Requirements: 5.3_
    - [x] 5.2 Create `src/CheckpointManager.php` with
      `shouldWrite(int $rowIndex): bool`, `write(...)`,
      `read(): ?CheckpointData`, `delete()`, and
      `generatePipelineId(array $stages): string` methods using atomic
      temp-file+rename writes
        - _Requirements: 5.1, 5.2, 5.4, 5.5, 5.6, 5.7_
          - [x]* 5.3 Write property test for checkpoint interval fires at
          correct row indices
        - **Property 10: Checkpoint interval fires at correct row indices**
        - **Validates: Requirements 5.2**
          - [x]* 5.4 Write property test for checkpoint data round-trip
          serialization
        - **Property 11: Checkpoint data round-trip serialization**
        - **Validates: Requirements 5.3**
          - [x]* 5.5 Write property test for deterministic pipeline ID
        - **Property 12: Deterministic pipeline ID**
        - **Validates: Requirements 5.4**

- [x]
    6. Schema validation - rules and parser

    - [x] 6.1 Create `src/Interfaces/ValidationRule.php` interface with
      `passes(mixed $value): bool` and `message(string $field): string`
        - _Requirements: 7.4_
    - [x] 6.2 Create `src/Rules/` directory with implementations: RequiredRule,
      StringRule, IntRule, FloatRule, EmailRule, MinRule, MaxRule, BetweenRule,
      InRule, RegexRule, ClosureRule
        - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10,
          8.11_
    - [x] 6.3 Create `src/RuleParser.php` to parse pipe-delimited rule strings
      into ValidationRule arrays
        - _Requirements: 7.3_
          - [x]* 6.4 Write property test for required rule semantics
        - **Property 16: Required rule semantics**
        - **Validates: Requirements 8.1**
          - [x]* 6.5 Write property test for type checking rules
        - **Property 17: Type checking rules**
        - **Validates: Requirements 8.2, 8.3, 8.4**
          - [x]* 6.6 Write property test for email rule matches filter_var
        - **Property 18: Email rule matches filter_var**
        - **Validates: Requirements 8.5**
          - [x]* 6.7 Write property test for numeric bound rules
        - **Property 19: Numeric bound rules**
        - **Validates: Requirements 8.6, 8.7, 8.8**
          - [x]* 6.8 Write property test for in-list rule
        - **Property 20: In-list rule**
        - **Validates: Requirements 8.9**
          - [x]* 6.9 Write property test for regex rule matches preg_match
        - **Property 21: Regex rule matches preg_match**
        - **Validates: Requirements 8.10**
          - [x]* 6.10 Write property test for optional field skips validation
        - **Property 22: Optional field skips validation when null or absent**
        - **Validates: Requirements 8.11**
          - [x]* 6.11 Write property test for closure rule invocation
        - **Property 15: Closure rule invocation**
        - **Validates: Requirements 7.7**

- [x]
    7. Schema validator processor

    - [x] 7.1 Create `src/Transformers/SchemaValidator.php` extending
      Transformer, accepting schema array, invoking RuleParser, iterating rows
      and applying rules per field, throwing ValidationException on failure
        - _Requirements: 7.1, 7.2, 7.5, 7.6, 7.7_
          - [x]* 7.2 Write property test for validation exception contains field
          and rule
        - **Property 14: Validation exception contains field and rule**
        - **Validates: Requirements 7.5**

- [x]
    8. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    9. Metrics exporter interface and implementations

    - [x] 9.1 Create `src/Interfaces/MetricsExporter.php` interface with
      recordRowProcessed, recordRowFailed, recordStageDuration,
      recordPipelineComplete methods
        - _Requirements: 9.1_
    - [x] 9.2 Create `src/Metrics/NullMetricsExporter.php` implementing
      MetricsExporter with no-op methods
        - _Requirements: 9.7_
    - [x] 9.3 Create `src/Metrics/LogMetricsExporter.php` accepting PSR-3
      LoggerInterface, logging info/warning level messages with event parameters
        - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6_
    - [x] 9.4 Create `src/Metrics/CallbackMetricsExporter.php` accepting
      optional closures per event, invoking them with event parameters or no-op
      when null
        - _Requirements: 11.1, 11.2, 11.3, 11.4_
          - [x]* 9.5 Write property test for metrics exporter receives correct
          event counts
        - **Property 23: Metrics exporter receives correct event counts**
        - **Validates: Requirements 9.3, 9.4, 9.5**
          - [x]* 9.6 Write property test for LogMetricsExporter log messages
          contain event parameters
        - **Property 24: LogMetricsExporter log messages contain event
          parameters**
        - **Validates: Requirements 10.3, 10.4, 10.5, 10.6**
          - [x]* 9.7 Write property test for CallbackMetricsExporter forwards
          parameters to closures
        - **Property 25: CallbackMetricsExporter forwards parameters to closures
          **
        - **Validates: Requirements 11.3**

- [x]
    10. Wire resilience features into Processor and DataFlow

    - [x] 10.1 Add
      `withRetry(int $maxAttempts, int $delay, bool $exponential, int $maxDelay): static`
      and `withCircuitBreaker(int $failureThreshold, int $cooldownMs): static`
      methods to `src/Processor.php`, storing RetryConfig and
      CircuitBreakerConfig
        - _Requirements: 1.4, 4.1, 4.4_
    - [x] 10.2 Add `withCheckpoint(string $path, int $interval): static`,
      `resume(): static`, `validate(array $schema): static`, and
      `withMetricsExporter(MetricsExporter $exporter): static` methods to
      `src/DataFlow.php`
        - _Requirements: 5.1, 5.5, 6.1, 7.1, 9.2_
    - [x] 10.3 Implement pipeline execution logic in DataFlow: integrate
      CheckpointManager for write/resume/delete, insert SchemaValidator stage
      via validate(), wire MetricsExporter calls (recordRowProcessed,
      recordRowFailed, recordStageDuration, recordPipelineComplete) at correct
      execution points in real-time
        - _Requirements: 5.2, 5.7, 6.2, 6.3, 6.4, 6.5, 6.6, 9.3, 9.4, 9.5, 9.6,
          9.7, 12.1, 12.2, 12.3, 12.4_
    - [x] 10.4 Implement StageRunner logic: apply RetryConfig with exponential
      backoff + jitter delays via usleep(), integrate CircuitBreaker per-stage (
      skip rows when Open, record to DeadLetterCollection with circuit-open
      reason), expose final CircuitState in PipelineResult
        - _Requirements: 3.2, 3.4, 4.5, 4.6_
          - [x]* 10.5 Write property test for open circuit records skipped rows
          in dead letters
        - **Property 9: Open circuit records skipped rows in dead letters**
        - **Validates: Requirements 4.6**
          - [x]* 10.6 Write property test for resume skips correct number of
          rows
        - **Property 13: Resume skips correct number of rows**
        - **Validates: Requirements 6.2**

- [x]
    11. Final checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design
  document
- All implementations use PHP standard library only — no new Composer
  dependencies
- Existing fluent API and Flowable contract remain unchanged

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "6.1"] },
    { "id": 1, "tasks": ["1.3", "3.1", "6.2", "9.1"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3", "2.4", "3.2", "3.3", "3.4", "3.5", "5.1", "6.3", "9.2", "9.3", "9.4"] },
    { "id": 3, "tasks": ["5.2", "6.4", "6.5", "6.6", "6.7", "6.8", "6.9", "6.10", "6.11", "9.5", "9.6", "9.7"] },
    { "id": 4, "tasks": ["5.3", "5.4", "5.5", "7.1"] },
    { "id": 5, "tasks": ["7.2", "10.1", "10.2"] },
    { "id": 6, "tasks": ["10.3", "10.4"] },
    { "id": 7, "tasks": ["10.5", "10.6"] }
  ]
}
```
