<?php

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * EmailRule - passes if value is a valid email address.
 */
final class EmailRule implements ValidationRule
{
    public function passes(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function message(string $field): string
    {
        return "The {$field} field must be a valid email address.";
    }
}
