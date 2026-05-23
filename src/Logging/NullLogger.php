<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Logging;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * NullLogger
 *
 * A PSR-3 compliant logger that discards all log messages.
 * Used as the default logger when no logger is configured,
 * imposing zero measurable overhead on pipeline execution.
 */
final class NullLogger extends AbstractLogger
{
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<mixed> $context
     *
     * @SuppressWarnings("unused")
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        // No-op: intentionally discards all log messages
    }
}
