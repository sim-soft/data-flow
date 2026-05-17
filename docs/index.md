---
title: Home
layout: home
nav_order: 0
---

# Simsoft DataFlow

A lightweight, composable ETL (Extract, Transform, Load) pipeline library for
PHP 8.2+ with fluent API, enterprise resilience, and spreadsheet support.

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

## Documentation

Browse the sidebar for complete guides on every feature.

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
