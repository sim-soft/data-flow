
# Circuit Breaker

Protect pipeline stages from cascading failures using the circuit breaker
pattern. When a stage fails repeatedly, the circuit opens and subsequent rows
are skipped until the service recovers.

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from($records)
    ->transform(
        (new ApiEnricher())
            ->withCircuitBreaker(failureThreshold: 5, cooldownMs: 10000)
            ->withErrorStrategy(ErrorStrategy::Skip)
            ->withName('api-enricher')
    )
    ->load(fn($row) => $row)
    ->run();
```

After 5 consecutive failures, the circuit opens. Rows arriving while the circuit
is open are immediately skipped and recorded in the dead-letter collection with
reason `"circuit-open"`.

## State Machine

```
     success          failure >= threshold
  ┌──────────┐      ┌──────────────────────┐
  │          ▼      ▼                      │
  │       CLOSED ──────► OPEN              │
  │          ▲              │              │
  │          │              │ cooldown     │
  │   success│              ▼              │
  │          └──── HALF-OPEN ──────────────┘
  │                         failure
```

| State        | Behavior                                                    |
|--------------|-------------------------------------------------------------|
| **Closed**   | All calls allowed. Failures increment counter.              |
| **Open**     | All calls blocked (rows dead-lettered). Waits for cooldown. |
| **HalfOpen** | One probe call allowed. Success → Closed. Failure → Open.   |

## Parameters

```php
->withCircuitBreaker(
    failureThreshold: 5,    // consecutive failures to open circuit (default: 5)
    cooldownMs: 10000,      // ms to wait before probe attempt (default: 10000)
)
```

## Combining with Retry

Circuit breaker and retry work together. Retries happen first; if all retries
are exhausted, the failure is recorded against the circuit breaker.

```php
(new ApiLoader())
    ->withRetry(maxAttempts: 3, delay: 200, exponential: true)
    ->withCircuitBreaker(failureThreshold: 5, cooldownMs: 30000)
    ->withErrorStrategy(ErrorStrategy::Skip)
    ->withName('api-loader')
```

Flow: attempt → retry (up to 3×) → if all fail → circuit breaker records
failure → after 5 such rows → circuit opens.

## Inspecting Circuit State

After pipeline execution, check the final circuit state per stage.

```php
$result = (new DataFlow())
    ->from($records)
    ->transform($stage->withCircuitBreaker(3, 5000)->withErrorStrategy(ErrorStrategy::Skip))
    ->load(fn($row) => $row)
    ->run();

foreach ($result->getCircuitStates() as $stageName => $state) {
    echo "{$stageName}: {$state->value}\n"; // "closed", "open", or "half_open"
}
```

## Dead Letters from Open Circuit

Rows skipped due to an open circuit appear in the dead-letter collection.

```php
foreach ($result->getDeadLetters() as $entry) {
    if ($entry->exception->getMessage() === 'circuit-open') {
        echo "Row {$entry->rowIndex} skipped (circuit open) in '{$entry->stageName}'\n";
    }
}
```
