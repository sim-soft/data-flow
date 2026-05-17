<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Interfaces\Flowable;
use Simsoft\DataFlow\Traits\Macroable;
use Simsoft\DataFlow\CircuitBreakerConfig;

/**
 * Processor
 */
abstract class Processor implements Flowable
{
    use Macroable;

    /** @var DataFlow The current flow object. */
    private DataFlow $flow;

    /** @var ErrorStrategy The error handling strategy for this processor. */
    private ErrorStrategy $errorStrategy = ErrorStrategy::Throw;

    /** @var RetryConfig|null The retry configuration when using the Retry strategy. */
    private ?RetryConfig $retryConfig = null;

    /** @var CircuitBreakerConfig|null The circuit breaker configuration for this processor. */
    private ?CircuitBreakerConfig $circuitBreakerConfig = null;

    /** @var string|null The human-readable name for this processor. */
    private ?string $name = null;

    /**
     * Set current flow.
     *
     * @param DataFlow $flow
     * @return $this
     */
    public function setFlow(DataFlow $flow): static
    {
        $this->flow = $flow;
        return $this;
    }

    /**
     * Get current flow object.
     *
     * @return DataFlow
     */
    public function getFlow(): DataFlow
    {
        return $this->flow;
    }

    /**
     * Set the error handling strategy for this processor.
     *
     * @param ErrorStrategy $strategy The error strategy to use.
     * @return static
     */
    public function withErrorStrategy(ErrorStrategy $strategy): static
    {
        $this->errorStrategy = $strategy;
        return $this;
    }

    /**
     * Configure this processor to use the retry error strategy with the given parameters.
     *
     * Sets the error strategy to Retry and creates a RetryConfig with the specified
     * maximum attempts, delay, exponential backoff, and delay cap.
     *
     * @param int $maxAttempts Maximum number of retry attempts (must be >= 1).
     * @param int $delay Base delay in milliseconds between retry attempts (must be >= 0).
     * @param bool $exponential Enable exponential backoff (true) or linear delay (false).
     * @param int $maxDelay Maximum delay cap in milliseconds (must be >= 1).
     * @return static
     */
    public function withRetry(int $maxAttempts = 3, int $delay = 100, bool $exponential = true, int $maxDelay = 30000): static
    {
        $this->errorStrategy = ErrorStrategy::Retry;
        $this->retryConfig = new RetryConfig($maxAttempts, $delay, $exponential, $maxDelay);
        return $this;
    }

    /**
     * Get the configured error strategy for this processor.
     *
     * @return ErrorStrategy
     */
    public function getErrorStrategy(): ErrorStrategy
    {
        return $this->errorStrategy;
    }

    /**
     * Get the retry configuration, if any.
     *
     * @return RetryConfig|null The retry config, or null if retry is not configured.
     */
    public function getRetryConfig(): ?RetryConfig
    {
        return $this->retryConfig;
    }

    /**
     * Configure a circuit breaker for this processor.
     *
     * When the circuit breaker is configured, the pipeline will track consecutive failures
     * and open the circuit (skip rows) when the failure threshold is reached.
     *
     * @param int $failureThreshold Number of consecutive failures before opening circuit (must be >= 1).
     * @param int $cooldownMs Milliseconds to wait in Open state before transitioning to HalfOpen (must be >= 1).
     * @return static
     */
    public function withCircuitBreaker(int $failureThreshold = 5, int $cooldownMs = 10000): static
    {
        $this->circuitBreakerConfig = new CircuitBreakerConfig($failureThreshold, $cooldownMs);
        return $this;
    }

    /**
     * Get the circuit breaker configuration, if any.
     *
     * @return CircuitBreakerConfig|null The circuit breaker config, or null if not configured.
     */
    public function getCircuitBreakerConfig(): ?CircuitBreakerConfig
    {
        return $this->circuitBreakerConfig;
    }

    /**
     * Set a human-readable name for this processor.
     *
     * @param string $name The processor name.
     * @return static
     */
    public function withName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the processor name.
     *
     * Returns the configured name, or the fully-qualified class name if no name was set.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name ?? static::class;
    }
}
