
# Getting Started

This page takes you from an empty directory to a working pipeline. Every snippet
here is complete and runnable — copy it into a file and run it.

## What is ETL?

ETL stands for **Extract, Transform, Load** — three steps that describe most data
movement work:

| Step          | Question it answers    | Examples                                 |
|---------------|------------------------|------------------------------------------|
| **Extract**   | Where does data come from? | A database table, a CSV, an API      |
| **Transform** | What needs to change?  | Rename columns, filter rows, validate    |
| **Load**      | Where does data go?    | Another database, a spreadsheet, a file  |

DataFlow gives you one method per step: `from()`, `transform()`, and `load()`.
Nothing runs until you call `run()`.

## Requirements

- PHP 8.3 or newer (check with `php -v`)
- [Composer](https://getcomposer.org)

Reading and writing spreadsheets, querying databases, and scanning directories
each need an extra package. They are optional — install one only when you use the
processor that needs it. See [Processors](02-USEFUL_PROCESSORS.md).

## Install

```bash
composer require simsoft/data-flow
```

## Your First Pipeline

Create `pipeline.php`:

```php
<?php

require 'vendor/autoload.php';

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3])              // Extract: three numbers
    ->transform(fn($n) => $n * 2)  // Transform: double each one
    ->load(function ($n) {         // Load: print each one
        echo $n . PHP_EOL;
    })
    ->run();                       // Nothing happens until this line
```

Run it:

```bash
php pipeline.php
```

```
2
4
6
```

## Something More Realistic

Rows are usually arrays, not numbers. This pipeline filters, reshapes, and
summarises a list of people:

```php
<?php

require 'vendor/autoload.php';

use Simsoft\DataFlow\DataFlow;

$people = [
    ['first' => 'John', 'last' => 'Doe', 'age' => 34],
    ['first' => 'Jane', 'last' => 'Doe', 'age' => 17],
    ['first' => 'Sam', 'last' => 'Smith', 'age' => 52],
];

$result = (new DataFlow())
    ->from($people)
    ->filter(fn(array $row) => $row['age'] >= 18)   // adults only
    ->map([
        'name' => fn(array $row) => $row['first'] . ' ' . $row['last'],
        'age' => 'age',
    ])
    ->load(function (array $row) {
        echo "{$row['name']} ({$row['age']})" . PHP_EOL;
    })
    ->run();

echo "Loaded {$result->getProcessedRows()} rows" . PHP_EOL;
```

```
John Doe (34)
Sam Smith (52)
Loaded 2 rows
```

`run()` returns a [`PipelineResult`](08-OBSERVABILITY.md) describing what
happened — row counts, duration, peak memory, and any failures.

## Inspecting a Pipeline

When a pipeline does not do what you expect, insert `preview()` to dump the rows
flowing through at that point, then stop:

```php
(new DataFlow())
    ->from($people)
    ->filter(fn(array $row) => $row['age'] >= 18)
    ->preview(1)   // show the first row reaching this point, then stop
    ->run();
```

```
Key: 0
Value: array (
  'first' => 'John',
  'last' => 'Doe',
  'age' => 34,
)
```

`preview()` replaces the rest of the pipeline, so remove it once you have your
answer.

## Closure Arguments

Every closure you pass to `from()`, `transform()`, `filter()`, or `load()`
receives up to three arguments. Declare only the ones you need:

```php
->load(function (mixed $data, int|string $key, Closure $exception) {
    // $data      — the row
    // $key       — the row's key (usually its position, but see below)
    // $exception — call it to abort the pipeline with a message
})
```

Keys are preserved as they flow through the pipeline. A plain list gives you
`0, 1, 2, …`, but a source that yields string keys keeps them — which is how
[SpoutLoader](02-USEFUL_PROCESSORS.md#SpoutLoader) writes rows to different
worksheets.

## Memory

DataFlow is built on generators, so rows are processed one at a time rather than
loaded into memory together. A pipeline over ten million rows uses roughly the
same memory as one over ten — as long as your extractor also streams. Passing a
pre-built array of ten million rows to `from()` costs whatever that array costs;
passing a generator or a streaming extractor does not.

## A Note on the Examples

Docs beyond this page use short placeholder names — `saveRow()`, `processRow()`,
`MyTransformer`, `DatabaseLoader` — to keep the focus on the feature being
explained. They stand for your own functions and classes and are not part of the
library, so those snippets illustrate rather than run as-is. Anything shipped
with DataFlow is written with its full namespace, like
`Simsoft\DataFlow\Loaders\SpoutLoader`.

## Where to Next

- [Using Closure](01-USING_CLOSURE.md) — closure arguments and flow control in depth
- [Processors](02-USEFUL_PROCESSORS.md) — the built-in extractors and loaders
- [Custom Processors](03-CUSTOMIZED_PROCESSOR.md) — writing your own
- [Error Handling](07-ERROR_HANDLING.md) — what happens when a row fails
