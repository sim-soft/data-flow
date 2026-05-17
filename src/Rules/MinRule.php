<?php

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * MinRule - passes if numeric value >= min.
 */
final class MinRule implements ValidationRule
{
    public function __construct(
        private readonly float $min,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        return is_numeric($value) && (float)$value >= $this->min;
    }

    public function message(string $field): string
    {
        return "The {$field} field must be at least {$this->min}.";
    }
}
