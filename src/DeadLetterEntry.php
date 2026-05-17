<?php

namespace Simsoft\DataFlow;

/**
 * DeadLetterEntry value object.
 *
 * Represents a single row that failed processing in the pipeline,
 * capturing the row data, failure context, and timestamp.
 */
final readonly class DeadLetterEntry
{
    /**
     * Constructor.
     *
     * @param mixed $row The original row data that failed processing.
     * @param string $stageName The name of the stage where the failure occurred.
     * @param int $rowIndex The zero-based index of the row in the input.
     * @param \Throwable $exception The exception that caused the failure.
     * @param \DateTimeImmutable $occurredAt The timestamp when the failure occurred.
     */
    public function __construct(
        public mixed              $row,
        public string             $stageName,
        public int                $rowIndex,
        public \Throwable         $exception,
        public \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    )
    {
    }
}
