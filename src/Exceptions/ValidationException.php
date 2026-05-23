<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Exceptions;

/**
 * ValidationException class.
 *
 * Thrown when a row field fails schema validation.
 */
class ValidationException extends DataFlowException
{
    public function __construct(
        private readonly string $fieldName,
        private readonly string $ruleName,
        string                  $message = '',
        int                     $code = 0,
        ?\Throwable             $previous = null,
    )
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the field name that failed validation.
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * Get the rule name that caused the failure.
     */
    public function getRuleName(): string
    {
        return $this->ruleName;
    }
}
