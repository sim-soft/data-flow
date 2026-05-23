<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * BetweenRule - passes if numeric value is between min and max (inclusive).
 */
final class BetweenRule implements ValidationRule
{
    public function __construct(
        private readonly float $min,
        private readonly float $max,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        $numericValue = (float)$value;

        return $numericValue >= $this->min && $numericValue <= $this->max;
    }

    public function message(string $field): string
    {
        return "The {$field} field must be between {$this->min} and {$this->max}.";
    }
}
