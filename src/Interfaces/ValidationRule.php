<?php

namespace Simsoft\DataFlow\Interfaces;

/**
 * ValidationRule Interface
 */
interface ValidationRule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param mixed $value
     * @return bool
     */
    public function passes(mixed $value): bool;

    /**
     * Get the validation error message.
     *
     * @param string $field
     * @return string
     */
    public function message(string $field): string;
}
