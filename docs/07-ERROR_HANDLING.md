
# Error Handling

The DataFlow library provides configurable per-stage error handling strategies,
allowing pipelines to gracefully handle failures without halting the entire
process.

## Error Strategies

Each processor (extractor, transformer, loader) can be configured with one of
four error strategies:

| Strategy         | Behavior                                        |
|------------------|-------------------------------------------------|
| `Throw`          | Propagate exception immediately (default)       |
| `Skip`           | Discard the failing row, continue with next     |
| `Retry`          | Re-attempt with backoff delay, then dead-letter |
| `LogAndContinue` | Log the error, pass original row through        |

## ⚠️ Caveat: Stateful Transformers

When the error strategy is anything other than `Throw`, `StageRunner` invokes
the stage **per-row** for error isolation: each row is wrapped in a single-row
`ArrayIterator` and passed to the stage individually. This breaks transformers
that buffer state across rows, such as
[`Chunk`](../src/Transformers/Chunk.php), because each invocation only ever
sees one row.

If a stage relies on cross-row state, either:

- Use `ErrorStrategy::Throw` (the default) for that stage, which preserves
  full-stream semantics and feeds the entire iterator to the stage in one call.
- Split the stateful step into a separate pipeline so the per-row error
  handling does not run against it.

```php
// ✅ Stateful: keep Throw so the full iterator reaches the transformer.
$pipeline->transform(
    (new Chunk(100))->withErrorStrategy(ErrorStrategy::Throw)
);

// ❌ Stateful + Skip/Retry/LogAndContinue: each row is wrapped solo,
// breaking the chunking buffer.
$pipeline->transform(
    (new Chunk(100))->withErrorStrategy(ErrorStrategy::Skip)
);
```

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Transformers\Mapping;

(new DataFlow())
    ->from($records)
    ->transform(
        (new Mapping([
            'amount' => fn($row) => $row['total'] / $row['quantity'], // may divide by zero
        ]))->withErrorStrategy(ErrorStrategy::Skip)
    )
    ->load(fn($row) => saveToDatabase($row))
    ->run();
```

Rows that cause a division-by-zero exception are silently skipped. The rest of
the pipeline continues normally.

## Skip Strategy

Discard failing rows and continue processing. Failed rows are recorded in the
dead-letter collection.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from([
        ['id' => 1, 'value' => 10],
        ['id' => 2, 'value' => 0],  // will cause division error
        ['id' => 3, 'value' => 5],
    ])
    ->transform(
        (new class extends \Simsoft\DataFlow\Transformer {
            public function __invoke(?\Iterator $dataFrame = null): \Iterator
            {
                foreach ($dataFrame as $row) {
                    yield ['id' => $row['id'], 'result' => 100 / $row['value']];
                }
            }
        })->withErrorStrategy(ErrorStrategy::Skip)->withName('divider')
    )
    ->load(fn($row) => $row)
    ->run();

echo "Processed: {$result->getProcessedRows()}\n";  // 2
echo "Failed: {$result->getFailedRows()}\n";         // 1
```

## Retry Strategy

Automatically retry failing rows with configurable backoff delay.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Loader;

$loader = new class extends Loader {
    private int $attempts = 0;

    public function __invoke(?\Iterator $dataFrame = null): \Iterator
    {
        foreach ($dataFrame as $row) {
            $this->attempts++;
            if ($this->attempts <= 2) {
                throw new \RuntimeException('Temporary failure');
            }
            yield $row;
        }
    }
};

// Retry up to 5 times with 50ms backoff between attempts
$loader->withRetry(maxAttempts: 5, delay: 50);

$result = (new DataFlow())
    ->from([['data' => 'important']])
    ->load($loader)
    ->run();
```

If all retry attempts are exhausted, the row is added to the dead-letter
collection.

### Exponential Backoff with Jitter

By default, retry uses exponential backoff: each attempt doubles the delay, with
±25% random jitter to prevent thundering herd.

```php
// Exponential backoff (default): 100ms → 200ms → 400ms → 800ms...
$processor->withRetry(maxAttempts: 5, delay: 100);

// With explicit parameters:
$processor->withRetry(
    maxAttempts: 5,
    delay: 100,           // base delay in ms
    exponential: true,    // enable exponential backoff (default)
    maxDelay: 30000,      // cap delay at 30 seconds (default)
);
```

**Delay formula (exponential):** `base_delay × 2^(attempt-1)`, clamped to
`maxDelay`, then ±25% jitter applied.

| Attempt | Base Delay | Computed | After Jitter (±25%) |
|---------|------------|----------|---------------------|
| 1       | 100ms      | 100ms    | 75–125ms            |
| 2       | 100ms      | 200ms    | 150–250ms           |
| 3       | 100ms      | 400ms    | 300–500ms           |
| 4       | 100ms      | 800ms    | 600–1000ms          |
| 5       | 100ms      | 1600ms   | 1200–2000ms         |

### Linear (Constant) Delay

Disable exponential backoff for a fixed delay between attempts. No jitter is
applied in linear mode.

```php
// Linear: always 500ms between attempts, no jitter
$processor->withRetry(
    maxAttempts: 3,
    delay: 500,
    exponential: false,
);
```

### Parameters

| Parameter     | Default | Description                                      |
|---------------|---------|--------------------------------------------------|
| `maxAttempts` | 3       | Total attempts including the first (must be ≥ 1) |
| `delay`       | 100     | Base delay in milliseconds (must be ≥ 0)         |
| `exponential` | true    | Enable exponential backoff with jitter           |
| `maxDelay`    | 30000   | Maximum delay cap in ms (must be ≥ 1)            |

## Log-and-Continue Strategy

Log the error and pass the original (unmodified) row through to the next stage.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('pipeline');
$logger->pushHandler(new StreamHandler('pipeline.log'));

$result = (new DataFlow())
    ->from($records)
    ->withLogger($logger)
    ->transform(
        (new MyTransformer())->withErrorStrategy(ErrorStrategy::LogAndContinue)
    )
    ->load(fn($row) => $row)
    ->run();

// All rows pass through — failing rows retain their original data.
// Errors are logged at warning/error level.
```

## Global Error Callback

Register a callback that fires for every non-throw error across all stages.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$errors = [];

$result = (new DataFlow())
    ->from($records)
    ->onError(function (\Throwable $exception, mixed $row, string $stageName) use (&$errors) {
        $errors[] = [
            'stage' => $stageName,
            'message' => $exception->getMessage(),
            'row' => $row,
        ];
    })
    ->transform(
        (new MyTransformer())
            ->withErrorStrategy(ErrorStrategy::Skip)
            ->withName('validation')
    )
    ->load(fn($row) => $row)
    ->run();

// $errors contains details of every skipped row
foreach ($errors as $error) {
    echo "Stage '{$error['stage']}': {$error['message']}\n";
}
```

## Dead-Letter Collection

Failed rows are collected in a dead-letter collection accessible from the
pipeline result.

```php
$result = (new DataFlow())
    ->from($records)
    ->transform($processor->withErrorStrategy(ErrorStrategy::Skip))
    ->load(fn($row) => $row)
    ->run();

$deadLetters = $result->getDeadLetters();

echo "Failed rows: {$deadLetters->count()}\n";

foreach ($deadLetters as $entry) {
    echo "Row {$entry->rowIndex} failed in '{$entry->stageName}': "
        . $entry->exception->getMessage() . "\n";
    // $entry->row contains the original row data
    // $entry->occurredAt is a DateTimeImmutable timestamp
}
```

## Naming Stages

Give processors meaningful names for better error reporting and logging.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from($records)
    ->transform(
        (new ValidateEmailTransformer())
            ->withName('email-validation')
            ->withErrorStrategy(ErrorStrategy::Skip)
    )
    ->transform(
        (new EnrichDataTransformer())
            ->withName('data-enrichment')
            ->withRetry(maxAttempts: 3, delay: 2000) // 2-second delay
    )
    ->load(
        (new DatabaseLoader())
            ->withName('db-writer')
            ->withErrorStrategy(ErrorStrategy::LogAndContinue)
    )
    ->run();
```

Stage names appear in log messages and dead-letter entries, making it easy to
identify where failures occurred.

## Combining Strategies

Different stages can use different strategies in the same pipeline.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from($rawData)
    // Validation: skip invalid rows
    ->transform(
        (new Validator())->withErrorStrategy(ErrorStrategy::Skip)->withName('validator')
    )
    // API enrichment: retry transient failures
    ->transform(
        (new ApiEnricher())->withRetry(maxAttempts: 3, delay: 500)->withName('api-enricher')
    )
    // Database write: log errors but don't lose data
    ->load(
        (new DbWriter())->withErrorStrategy(ErrorStrategy::LogAndContinue)->withName('db-writer')
    )
    ->run();

echo "Processed: {$result->getProcessedRows()}\n";
echo "Failed: {$result->getFailedRows()}\n";

// Inspect failures by stage
foreach ($result->getDeadLetters() as $entry) {
    echo "[{$entry->stageName}] Row {$entry->rowIndex}: {$entry->exception->getMessage()}\n";
}
```
