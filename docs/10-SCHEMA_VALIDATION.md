---
title: Schema Validation
parent: Features
nav_order: 2
---

# Schema Validation

Validate row data inline using pipe-delimited rule strings, similar to Laravel's
validation syntax.

## Basic Usage

```php
use Simsoft\DataFlow\DataFlow;

$result = (new DataFlow())
    ->from($records)
    ->validate([
        'name'  => 'required|string',
        'email' => 'required|email',
        'age'   => 'required|int|min:0|max:150',
    ])
    ->load(fn($row) => saveRow($row))
    ->run();
```

Rows that fail validation throw a `ValidationException`. Combine with error
strategies to skip or collect invalid rows.

## With Error Strategy

```php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Transformers\SchemaValidator;

$result = (new DataFlow())
    ->from($records)
    ->transform(
        (new SchemaValidator([
            'email' => 'required|email',
            'score' => 'required|int|between:0,100',
        ]))->withErrorStrategy(ErrorStrategy::Skip)->withName('validator')
    )
    ->load(fn($row) => $row)
    ->run();

echo "Valid: {$result->getProcessedRows()}\n";
echo "Invalid: {$result->getFailedRows()}\n";
```

## Available Rules

| Rule     | Syntax            | Passes when                                           |
|----------|-------------------|-------------------------------------------------------|
| required | `required`        | Value is not null, not empty string, not empty array  |
| string   | `string`          | `is_string($value)`                                   |
| int      | `int`             | `is_int($value)`                                      |
| float    | `float`           | `is_float($value) \|\| is_int($value)`                |
| email    | `email`           | `filter_var($value, FILTER_VALIDATE_EMAIL) !== false` |
| min      | `min:N`           | `is_numeric($value) && (float)$value >= N`            |
| max      | `max:N`           | `is_numeric($value) && (float)$value <= N`            |
| between  | `between:M,N`     | `is_numeric($value) && M <= (float)$value <= N`       |
| in       | `in:a,b,c`        | Value is in the comma-separated list                  |
| regex    | `regex:/pattern/` | `preg_match($pattern, $value) === 1`                  |

## Optional Fields

Fields without `required` are optional. If absent or null, all other rules are
skipped.

```php
->validate([
    'name'     => 'required|string',       // must be present
    'nickname' => 'string',                 // skipped if absent or null
    'age'      => 'int|min:0',             // skipped if absent or null
])
```

## Closure Rules

Use closures for custom validation logic.

```php
->validate([
    'score' => ['required', 'int', fn($v) => $v >= 0 && $v <= 100],
    'code'  => fn($v) => preg_match('/^[A-Z]{3}-\d{4}$/', $v) === 1,
])
```

## ValidationException

When validation fails, a `ValidationException` is thrown with field and rule
details.

```php
use Simsoft\DataFlow\Exceptions\ValidationException;

try {
    $result = (new DataFlow())->from($data)->validate($schema)->load(fn($r) => $r)->run();
} catch (ValidationException $e) {
    echo "Field: {$e->getFieldName()}\n";   // e.g. "email"
    echo "Rule: {$e->getRuleName()}\n";     // e.g. "email"
    echo "Message: {$e->getMessage()}\n";   // e.g. "The email field must be a valid email address."
}
```

## Standalone SchemaValidator

Use `SchemaValidator` directly as a transformer for more control.

```php
use Simsoft\DataFlow\Transformers\SchemaValidator;

$validator = new SchemaValidator([
    'amount' => 'required|float|min:0.01',
    'currency' => 'required|in:USD,EUR,GBP',
]);

(new DataFlow())
    ->from($transactions)
    ->transform($validator->withErrorStrategy(ErrorStrategy::Skip)->withName('schema'))
    ->load(fn($row) => $row)
    ->run();
```
