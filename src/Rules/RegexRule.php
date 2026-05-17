<?php

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * RegexRule - passes if value matches the given regex pattern.
 */
final class RegexRule implements ValidationRule
{
    public function __construct(
        private readonly string $pattern,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match($this->pattern, $value) === 1;
    }

    public function message(string $field): string
    {
        return "The {$field} field format is invalid.";
    }
}
