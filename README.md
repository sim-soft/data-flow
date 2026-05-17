# Introduction

A lightweight, composable ETL (Extract, Transform, Load) pipeline library for
PHP 8.2+ with fluent API, error handling, observability, and spreadsheet
support.

## Why This Library

- **Fluent, composable API** — chain extractors, transformers, and loaders in a
  single readable expression
- **Built-in resilience** — retry with exponential backoff + jitter, circuit
  breaker, and checkpoint/resume without external dependencies
- **Zero-overhead opt-in** — every resilience feature uses the null object
  pattern; disabled features cost nothing at runtime
- **Generator-based streaming** — constant memory footprint regardless of
  dataset size
- **Per-stage error strategies** — configure Skip, Retry, Throw, or
  LogAndContinue independently on each stage
- **Crash recovery** — checkpoint/resume enables long-running pipelines to
  recover from failures without reprocessing from scratch
- **Circuit breaker** — prevents cascading failures when downstream services
  degrade, a pattern common in microservices (Resilience4j, Polly) but unique
  among PHP ETL libraries
- **Dead letter collection** — failed and circuit-open rows are captured with
  full context for inspection or reprocessing
- **Inline schema validation** — validate row data with pipe-delimited rules (
  Laravel-style syntax) without leaving the pipeline
- **Real-time metrics** — pluggable MetricsExporter interface for emitting
  events to logging, StatsD, Prometheus, or custom systems
- **Dry-run mode** — validate entire pipelines without performing actual writes

## Install

```shell
composer require simsoft/data-flow
```

## Basic Usage

Example using extract, transform and load.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3])
    ->transform(function($num) {
        return $num * 2;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
```

## Limit

Limit data output.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->transform(function($num) {
        return $num * 2;
    })
    ->limit(5)  // output only 5 data.
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
// 8
// 10
```

## Filter
Filter method help you to filter the data.
```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->filter(function($num) {
        // The call back should return bool.
        // In this case, return even number only.
        return $num % 2 === 0;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
// 8
// 10
```

## Chunk

Splitting data into smaller, manageable parts of a fixed size

```php
(new DataFlow())
    ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->chunk(3) // set chunk size
    ->load(function(array $chunk, $key) {
        echo $key . '=' . json_encode($chunk, JSON_THROW_ON_ERROR) . PHP_EOL;
    })
    ->run();

// Output:
// 0=[1,2,3]
// 1=[4,5,6]
// 2=[7,8,9]
// 3=[10]
```

## Mapping

Mapping method allow you to convey the data to another format. Original keys are
preserved; mapped keys are added or overwritten.

```php
(new DataFlow())
    ->from([
        ['First Name' => 'John', 'Last Name' => 'Doe', 'age' => 20],
        ['First Name' => 'Jane', 'Last Name' => 'Doe', 'age' => 30],
        ['First Name' => 'John', 'Last Name' => 'Smith', 'age' => 50],
        ['First Name' => 'Jane', 'Last Name' => 'Smith', 'age' => 60],
    ])
    ->map([
        // rename the key
        'first_name' => 'First Name',
        'last_name' => 'Last Name',

        // customise data via callback method.
        'full_name' => fn($data) => $data['first_name'] . ' ' . $data['last_name'],
        'senior' => fn($data) => $data['age'] > 30 ? 'Yes' : 'No',
    ])
    ->load(function($data) {
        echo $data['full_name'] . ' is ' . $data['age'] . ' years old. ' . $data['senior'] . PHP_EOL;
    })
    ->run();

// Output:
// John Doe is 20 years old. No
// Jane Doe is 30 years old. Yes
// John Smith is 50 years old. Yes
// Jane Smith is 60 years old. Yes
```

## Set New Map

`setNewMap()` converts source data into a completely new array containing **only
** the mapped keys. Unlike `map()` which merges into the existing row,
`setNewMap()` discards all original keys.

```php
(new DataFlow())
    ->from([
        ['first_name' => 'John', 'last_name' => 'Doe', 'age' => 20, 'status' => 'active', 'internal_id' => 'x99'],
        ['first_name' => 'Jane', 'last_name' => 'Smith', 'age' => 30, 'status' => 'inactive', 'internal_id' => 'x42'],
    ])
    ->setNewMap([
        'name' => fn($row) => $row['first_name'] . ' ' . $row['last_name'],
        'age' => 'age',
    ])
    ->load(function($data) {
        // $data contains ONLY 'name' and 'age' — no 'status', 'internal_id', etc.
        echo json_encode($data) . PHP_EOL;
    })
    ->run();

// Output:
// {"name":"John Doe","age":20}
// {"name":"Jane Smith","age":30}
```

### map() vs setNewMap()

|                 | `map()`                                   | `setNewMap()`                                      |
|-----------------|-------------------------------------------|----------------------------------------------------|
| Original keys   | Preserved                                 | Discarded                                          |
| Result contains | All original keys + mapped keys           | Only mapped keys                                   |
| Use case        | Add/rename columns while keeping the rest | Reshape into a new structure, drop unwanted fields |

## Preview

`preview()` is a debugging helper that limits the pipeline to N rows and dumps
each row's key and value. Use it to inspect the data structure at any point in
the pipeline.

```php
(new DataFlow())
    ->from([
        ['name' => 'John', 'email' => 'john@example.com'],
        ['name' => 'Jane', 'email' => 'jane@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ])
    ->map(['full_name' => fn($row) => strtoupper($row['name'])])
    ->preview(2) // show first 2 rows then stop
    ->run();

// Output:
// Key: int(0)
// Value: array(3) { ["name"]=> "John", ["email"]=> "john@example.com", ["full_name"]=> "JOHN" }
//
// Key: int(1)
// Value: array(3) { ["name"]=> "Jane", ["email"]=> "jane@example.com", ["full_name"]=> "JANE" }
```

Insert `preview()` at any point to understand the data shape before writing the
next stage.
## Flow Continuation

Connecting flows into a chain.

```php
$flow1 = (new DataFlow())
    ->from([1, 2, 3])
    ->transform(function($num) {
        return $num * 2;
    });

(new DataFlow())
    ->from($flow1) // connect flow1 to flow2.
    ->transform(function($num) {
        return $num * 3;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 6
// 12
// 18
```

## Pipeline Result

Every `run()` call returns a `PipelineResult` with execution metadata.

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from([1, 2, 3, 4, 5])
    ->transform(fn($n) => $n * 2)
    ->load(fn($n) => $n)
    ->run();

echo "Processed: {$result->getProcessedRows()} rows\n";
echo "Duration: " . round($result->getDurationMs()) . "ms\n";
echo "Peak memory: " . round($result->getPeakMemoryBytes() / 1024) . " KB\n";
```

## Error Handling

Configure per-stage error strategies for production resilience.

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;

$result = (new DataFlow())
    ->from($records)
    ->transform(
        (new MyTransformer())
            ->withErrorStrategy(ErrorStrategy::Skip) // skip failing rows
            ->withName('validator')
    )
    ->load(fn($row) => $row)
    ->run();

echo "Processed: {$result->getProcessedRows()}\n";
echo "Failed: {$result->getFailedRows()}\n";
```

Available strategies: `Throw` (default), `Skip`, `Retry`, `LogAndContinue`.

## Dry-Run Mode

Validate pipelines without performing actual writes.

```php
$result = (new DataFlow())
    ->from($records)
    ->transform(fn($row) => $row)
    ->load(new DatabaseLoader())
    ->dryRun()
    ->run();

echo "Would process: {$result->getProcessedRows()} rows\n";
// No data was actually written
```

## Logging & Progress

Inject a PSR-3 logger and track progress on large datasets.

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($largeDataset)
    ->withLogger($psrLogger)
    ->onProgress(function (int $count, float $elapsedMs) {
        echo "\r  Processed {$count} rows...";
    }, interval: 1000)
    ->onError(function (\Throwable $e, mixed $row, string $stage) {
        error_log("[{$stage}] {$e->getMessage()}");
    })
    ->transform(fn($row) => $row)
    ->load(fn($row) => $row)
    ->run();
```

## Advanced Usage

1. [Using Closure](docs/01-USING_CLOSURE.md)
2. [Useful Processors](docs/02-USEFUL_PROCESSORS.md)
3. [Customized ETL Processor](docs/03-CUSTOMIZED_PROCESSOR.md)
4. [Create Reusable Data Flow](docs/04-CONTROLLABLE_DATAFLOW.md)
5. [Using Payload](docs/05-USING_PAYLOAD.md)
6. [Macro & Mixin](docs/06-MACRO_AND_MIXIN.md)
7. [Error Handling](docs/07-ERROR_HANDLING.md)
8. [Observability & Metrics](docs/08-OBSERVABILITY.md)
9. [Dry-Run Mode](docs/09-DRY_RUN.md)
10. [Schema Validation](docs/10-SCHEMA_VALIDATION.md)
11. [Circuit Breaker](docs/11-CIRCUIT_BREAKER.md)
12. [Checkpoint & Resume](docs/12-CHECKPOINT_RESUME.md)
13. [Metrics Exporter](docs/13-METRICS_EXPORTER.md)
14. [Spreadsheet (PhpSpreadsheet)](docs/14-SPREADSHEET.md)

## License

The Simsoft DataFlow is licensed under the MIT License. See
the [LICENSE](LICENSE) file for details
