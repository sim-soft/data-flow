<?php

namespace Simsoft\DataFlow;

use InvalidArgumentException;

/**
 * RetryConfig
 *
 * Immutable value object for retry strategy configuration.
 * Supports exponential backoff with jitter and linear (constant) delay modes.
 */
final readonly class RetryConfig
{
    /**
     * @param int $maxAttempts Maximum number of retry attempts (must be >= 1)
     * @param int $delay Base delay in milliseconds between retry attempts (must be >= 0)
     * @param bool $exponential Enable exponential backoff (true) or linear delay (false)
     * @param int $maxDelay Maximum delay cap in milliseconds (must be >= 1)
     *
     * @throws InvalidArgumentException If maxAttempts < 1, delay < 0, or maxDelay < 1
     */
    public function __construct(
        public int  $maxAttempts = 3,
        public int  $delay = 100,
        public bool $exponential = true,
        public int  $maxDelay = 30000,
    )
    {
        if ($this->maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts must be >= 1');
        }

        if ($this->delay < 0) {
            throw new InvalidArgumentException('delay must be >= 0');
        }

        if ($this->maxDelay < 1) {
            throw new InvalidArgumentException('maxDelay must be >= 1');
        }
    }

    /**
     * Compute delay for a given attempt number (1-based).
     *
     * When exponential=true: base_delay × 2^(attempt-1), clamped to maxDelay.
     * When exponential=false: returns the constant base delay.
     */
    public function computeDelay(int $attempt): int
    {
        if (!$this->exponential) {
            return $this->delay;
        }

        $computed = (int)($this->delay * (2 ** ($attempt - 1)));

        return min($computed, $this->maxDelay);
    }

    /**
     * Apply ±25% random jitter to a computed delay.
     *
     * Only applies jitter when exponential mode is enabled.
     * In linear mode, returns the delay unchanged.
     * Result is always >= 1ms.
     */
    public function applyJitter(int $delayMs): int
    {
        if (!$this->exponential) {
            return $delayMs;
        }

        $min = (int)round($delayMs * 0.75);
        $max = (int)round($delayMs * 1.25);

        $jittered = random_int($min, $max);

        return max(1, min($jittered, $this->maxDelay));
    }
}
