<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * RequiredRule - fails if value is null, empty string, or empty array.
 */
final class RequiredRule implements ValidationRule
{
    public function passes(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if ($value === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }

    public function message(string $field): string
    {
        return "The {$field} field is required.";
    }
}
