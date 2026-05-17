---
title: Home
layout: home
nav_order: 0
---

# Simsoft DataFlow

A lightweight, composable ETL (Extract, Transform, Load) pipeline library for
PHP 8.2+ with fluent API, enterprise resilience, and spreadsheet support.

## Install

```shell
composer require simsoft/data-flow
```

## Quick Start

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

## Why This Library

- **Fluent, composable API** — chain extractors, transformers, and loaders in a
  single readable expression
- **Built-in resilience** — retry with exponential backoff + jitter, circuit
  breaker, and checkpoint/resume
- **Zero-overhead opt-in** — disabled features cost nothing at runtime (null
  object pattern)
- **Generator-based streaming** — constant memory footprint regardless of
  dataset size
- **Per-stage error strategies** — Skip, Retry, Throw, or LogAndContinue
  independently on each stage
- **Inline schema validation** — validate row data with pipe-delimited rules (
  Laravel-style syntax)
- **Real-time metrics** — pluggable MetricsExporter for logging, StatsD,
  Prometheus, or custom systems
- **Dry-run mode** — validate pipelines without performing actual writes

## Documentation

Browse the sidebar for complete guides on every feature.
