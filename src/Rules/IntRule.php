<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * IntRule - passes if value is an integer.
 */
final class IntRule implements ValidationRule
{
    public function passes(mixed $value): bool
    {
        return is_int($value);
    }

    public function message(string $field): string
    {
        return "The {$field} field must be an integer.";
    }
}
