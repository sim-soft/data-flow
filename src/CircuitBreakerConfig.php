<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

use InvalidArgumentException;

/**
 * CircuitBreakerConfig
 *
 * Immutable value object for circuit breaker configuration.
 * Defines the failure threshold and cooldown period for the circuit breaker state machine.
 */
final readonly class CircuitBreakerConfig
{
    /**
     * @param int $failureThreshold Number of consecutive failures before opening circuit (must be >= 1)
     * @param int $cooldownMs Milliseconds to wait in Open state before transitioning to HalfOpen (must be >= 1)
     *
     * @throws InvalidArgumentException If failureThreshold < 1 or cooldownMs < 1
     */
    public function __construct(
        public int $failureThreshold = 5,
        public int $cooldownMs = 10000,
    )
    {
        if ($this->failureThreshold < 1) {
            throw new InvalidArgumentException('failureThreshold must be >= 1');
        }

        if ($this->cooldownMs < 1) {
            throw new InvalidArgumentException('cooldownMs must be >= 1');
        }
    }
}
