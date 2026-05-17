<?php

namespace Simsoft\DataFlow\Rules;

use Simsoft\DataFlow\Interfaces\ValidationRule;

/**
 * InRule - passes if value is in the allowed list.
 */
final class InRule implements ValidationRule
{
    /** @param array<int, mixed> $allowed */
    public function __construct(
        private readonly array $allowed,
    )
    {
    }

    public function passes(mixed $value): bool
    {
        return in_array($value, $this->allowed, strict: false);
    }

    public function message(string $field): string
    {
        $list = implode(', ', $this->allowed);

        return "The {$field} field must be one of: {$list}.";
    }
}
