<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * StringRule - passes if value is a string.
 */
final class StringRule implements ValidationRule
{
    public function passes(mixed $value): bool
    {
        return is_string($value);
    }

    public function message(string $field): string
    {
        return "The {$field} field must be a string.";
    }
}
