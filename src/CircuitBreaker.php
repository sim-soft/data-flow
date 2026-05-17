<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Enums\CircuitState;

/**
 * CircuitBreaker
 *
 * Stateful per-stage circuit breaker implementing the Closed → Open → HalfOpen state machine.
 * Uses hrtime(true) for nanosecond-precision cooldown timing.
 */
final class CircuitBreaker
{
    private CircuitState $state = CircuitState::Closed;

    private int $consecutiveFailures = 0;

    /** @var int|null Nanosecond timestamp when circuit transitioned to Open */
    private ?int $openedAtNs = null;

    public function __construct(
        private readonly CircuitBreakerConfig $config,
    )
    {
    }

    public function getState(): CircuitState
    {
        return $this->state;
    }

    /**
     * Determine whether a call is allowed through the circuit breaker.
     *
     * - Closed: always allowed
     * - Open: blocked unless cooldown has elapsed (transitions to HalfOpen)
     * - HalfOpen: allowed (one probe call)
     */
    public function isCallAllowed(): bool
    {
        return match ($this->state) {
            CircuitState::Closed => true,
            CircuitState::Open => $this->tryTransitionToHalfOpen(),
            CircuitState::HalfOpen => true,
        };
    }

    /**
     * Record a successful call.
     *
     * - Closed: resets failure counter
     * - HalfOpen: transitions to Closed and resets failure counter
     */
    public function recordSuccess(): void
    {
        match ($this->state) {
            CircuitState::Closed, CircuitState::HalfOpen => $this->reset(),
            CircuitState::Open => null,
        };
    }

    /**
     * Record a failed call.
     *
     * - Closed: increments failure counter; if threshold reached, transitions to Open
     * - HalfOpen: transitions immediately to Open
     */
    public function recordFailure(): void
    {
        match ($this->state) {
            CircuitState::Closed => $this->handleClosedFailure(),
            CircuitState::HalfOpen => $this->transitionToOpen(),
            CircuitState::Open => null,
        };
    }

    /**
     * Check if cooldown has elapsed and transition to HalfOpen if so.
     */
    private function tryTransitionToHalfOpen(): bool
    {
        $cooldownNs = $this->config->cooldownMs * 1_000_000;
        $elapsedNs = hrtime(true) - $this->openedAtNs;

        if ($elapsedNs >= $cooldownNs) {
            $this->state = CircuitState::HalfOpen;
            return true;
        }

        return false;
    }

    /**
     * Handle a failure in the Closed state.
     */
    private function handleClosedFailure(): void
    {
        $this->consecutiveFailures++;

        if ($this->consecutiveFailures >= $this->config->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    /**
     * Transition to Open state and record the timestamp.
     */
    private function transitionToOpen(): void
    {
        $this->state = CircuitState::Open;
        $this->openedAtNs = hrtime(true);
    }

    /**
     * Reset to Closed state with zero failures.
     */
    private function reset(): void
    {
        $this->state = CircuitState::Closed;
        $this->consecutiveFailures = 0;
        $this->openedAtNs = null;
    }
}
