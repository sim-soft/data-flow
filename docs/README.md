# Simsoft DataFlow

[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://github.com/sim-soft/data-flow/blob/main/LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.3-8892BF.svg)](https://php.net)

> A lightweight, composable ETL pipeline library for PHP 8.3+

**DataFlow** helps you move data from one place to another — read from a
source (database, CSV, API), transform it (filter, map, validate, enrich), and
write it to a destination (database, spreadsheet, file). This pattern is called
**ETL** (Extract, Transform, Load) and is the backbone of data migration,
reporting, syncing, and batch processing.

With DataFlow, you describe your pipeline as a fluent chain:

```php
(new DataFlow())
    ->from($source)         // Extract: where data comes from
    ->transform($logic)     // Transform: reshape, filter, validate
    ->load($destination)    // Load: where data goes
    ->run();
```

No framework required. No external services. Just PHP.

# Install

```bash
composer require simsoft/data-flow
```

# Quick Start

```php
use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3, 4, 5])
    ->transform(fn($n) => $n * 2)
    ->filter(fn($n) => $n > 4)
    ->load(fn($n) => echo $n . PHP_EOL)
    ->run();

// Output: 6, 8, 10
```

# Why This Library

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
  degrade, a pattern common in microservices but unique among PHP ETL libraries
- **Dead letter collection** — failed and circuit-open rows are captured with
  full context for inspection or reprocessing
- **Inline schema validation** — validate row data with pipe-delimited rules (
  Laravel-style syntax) without leaving the pipeline
- **Real-time metrics** — pluggable MetricsExporter interface for emitting
  events to logging, StatsD, Prometheus, or custom systems
- **Dry-run mode** — validate entire pipelines without performing actual writes
