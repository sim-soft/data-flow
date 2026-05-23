<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * FloatRule - passes if value is a float or integer.
 */
final class FloatRule implements ValidationRule
{
    public function passes(mixed $value): bool
    {
        return is_float($value) || is_int($value);
    }

    public function message(string $field): string
    {
        return "The {$field} field must be a float.";
    }
}
