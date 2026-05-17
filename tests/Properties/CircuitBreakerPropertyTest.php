<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CircuitBreaker;
use Simsoft\DataFlow\CircuitBreakerConfig;
use Simsoft\DataFlow\Enums\CircuitState;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * CircuitBreakerPropertyTest class
 *
 * Property-based tests for circuit breaker state machine using randomized inputs.
 *
 * Feature: enterprise-resilience, Property 6: Failure threshold triggers Open state
 */
#[CoversClass(CircuitBreaker::class)]
class CircuitBreakerPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random failure threshold values.
     *
     * Each case provides a random failureThreshold (1-100) to verify that
     * exactly that many consecutive failures triggers the Open state.
     *
     * @return Generator
     */
    public static function failureThresholdProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $failureThreshold = random_int(1, 100);

            yield "threshold={$failureThreshold},i={$i}" => [
                $failureThreshold,
            ];
        }
    }

    /**
     * Property 6: Failure threshold triggers Open state
     *
     * For any CircuitBreaker with failure threshold N, recording exactly N
     * consecutive failures from the Closed state SHALL transition the state to Open.
     * After fewer than N failures, the state remains Closed.
     *
     * **Validates: Requirements 3.3**
     *
     * @param int $failureThreshold The number of consecutive failures to trigger Open state.
     * @return void
     */
    #[Test]
    #[DataProvider('failureThresholdProvider')]
    public function failureThresholdTriggersOpenState(int $failureThreshold): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: $failureThreshold);
        $breaker = new CircuitBreaker($config);

        // Verify initial state is Closed
        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            'Circuit breaker should start in Closed state'
        );

        // Record (threshold - 1) failures — state should remain Closed
        for ($i = 1; $i < $failureThreshold; $i++) {
            $breaker->recordFailure();
            $this->assertSame(
                CircuitState::Closed,
                $breaker->getState(),
                "After {$i} failures (threshold={$failureThreshold}), state should remain Closed"
            );
        }

        // Record the Nth failure — state should transition to Open
        $breaker->recordFailure();
        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            "After exactly {$failureThreshold} consecutive failures, state should be Open"
        );
    }
}
