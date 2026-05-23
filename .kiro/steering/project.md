# Project Context

**simsoft/data-flow** — composable ETL pipeline library for PHP ≥ 8.3.

## Architecture

- `Flowable` contract: `__invoke(?Iterator): Iterator`
- `Processor` (abstract) → `Extractor`, `Transformer`, `Loader`
- `DataFlow` chains processors; closures auto-wrap in `CallableProcessor`
- `Signal::Stop` halts iteration; `Signal::Next` skips row
- `final` constructor on `DataFlow`; subclasses override `init()`

## Fluent API

```php
(new DataFlow())->from($src)->transform($fn)->filter($fn)->map($m)->chunk($n)->limit($n)->load($fn)->run();
```

## Source Layout

```
src/                          # PSR-4: Simsoft\DataFlow\
├── DataFlow.php              # Orchestrator
├── Processor.php             # Abstract base
├── Extractor.php / Transformer.php / Loader.php
├── CallableProcessor.php     # Closure wrapper
├── Payload.php               # Shared state
├── Enums/Signal.php
├── Exceptions/               # DataFlowException (base), Extractor/Transformer/Loader/InvalidCallable
├── Extractors/               # Iterable, Spout, Spreadsheet, ActiveQuery, FileFinder
├── Transformers/             # Chunk, Filter, Mapping
├── Loaders/                  # Preview, Visualize, Spout, Spreadsheet
├── Interfaces/Flowable.php
└── Traits/                   # DataFrame, CallableDataFrame, Macroable
simsoft/                      # Vendored packages (FLIQ ORM, Spreadsheet)
tests/                        # PHPUnit suite
```

## Dependencies

See `composer.json`. Key: `phpoffice/phpspreadsheet`, `league/flysystem`,
`symfony/cache`.
Vendored: `simsoft/fliq` (DB ORM), `simsoft/spreadsheet`.

## Commands

```bash
composer test   # PHPUnit
composer qc     # PHPStan (level 8) + PHPMD
```
