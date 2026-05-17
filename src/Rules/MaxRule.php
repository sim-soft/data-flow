<?php

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * MaxRule - passes if numeric value <= max.
 */
final class MaxRule implements ValidationRule
{
    public function __construct(
        private readonly float $max,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        return is_numeric($value) && (float)$value <= $this->max;
    }

    public function message(string $field): string
    {
        return "The {$field} field must not be greater than {$this->max}.";
    }
}
