<?php

namespace Simsoft\DataFlow\Rules;

use Closure;
use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * ClosureRule - passes if the closure returns true for the given value.
 */
final class ClosureRule implements ValidationRule
{
    public function __construct(
        private readonly Closure $callback,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        return ($this->callback)($value) === true;
    }

    public function message(string $field): string
    {
        return "The {$field} field is invalid.";
    }
}
