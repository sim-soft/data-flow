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
 * CircuitBreakerHalfOpenPropertyTest
 *
 * Property-based test verifying that the HalfOpen probe outcome determines
 * the circuit breaker's next state transition.
 *
 * Feature: enterprise-resilience, Property 8: HalfOpen probe outcome determines transition
 */
#[CoversClass(CircuitBreaker::class)]
class CircuitBreakerHalfOpenPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random failure threshold values for HalfOpen success scenario.
     *
     * @return Generator
     */
    public static function halfOpenSuccessProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $failureThreshold = random_int(1, 50);

            yield "threshold={$failureThreshold},i={$i}" => [
                $failureThreshold,
            ];
        }
    }

    /**
     * Data provider generating 100 random failure threshold values for HalfOpen failure scenario.
     *
     * @return Generator
     */
    public static function halfOpenFailureProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $failureThreshold = random_int(1, 50);

            yield "threshold={$failureThreshold},i={$i}" => [
                $failureThreshold,
            ];
        }
    }

    /**
     * Property 8: HalfOpen probe success transitions to Closed
     *
     * For any CircuitBreaker in the HalfOpen state, recording a success
     * SHALL transition to Closed with failure counter reset to zero.
     *
     * **Validates: Requirements 3.7, 3.8**
     *
     * @param int $failureThreshold The configured failure threshold.
     * @return void
     */
    #[Test]
    #[DataProvider('halfOpenSuccessProvider')]
    public function halfOpenProbeSuccessTransitionsToClosed(int $failureThreshold): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: $failureThreshold,
            cooldownMs: 1,
        );
        $breaker = new CircuitBreaker($config);

        // Drive to Open state by recording failureThreshold consecutive failures
        for ($i = 0; $i < $failureThreshold; $i++) {
            $breaker->recordFailure();
        }

        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            "Circuit should be Open after {$failureThreshold} failures"
        );

        // Wait for cooldown to elapse (1ms cooldown + 2ms sleep)
        usleep(5000);

        // isCallAllowed() should transition from Open to HalfOpen
        $allowed = $breaker->isCallAllowed();

        $this->assertTrue($allowed, 'Call should be allowed after cooldown (HalfOpen probe)');
        $this->assertSame(
            CircuitState::HalfOpen,
            $breaker->getState(),
            'Circuit should be in HalfOpen state after cooldown elapsed'
        );

        // Record success in HalfOpen — should transition to Closed
        $breaker->recordSuccess();

        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            'Circuit should transition to Closed after success in HalfOpen'
        );

        // Verify failure counter was reset: it should take a full failureThreshold
        // failures to open the circuit again
        for ($i = 0; $i < $failureThreshold - 1; $i++) {
            $breaker->recordFailure();
        }

        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            "Circuit should remain Closed after {$failureThreshold}-1 failures "
            . "(proving counter was reset to zero)"
        );

        $breaker->recordFailure();

        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            "Circuit should open after exactly {$failureThreshold} failures post-reset"
        );
    }

    /**
     * Property 8: HalfOpen probe failure transitions to Open
     *
     * For any CircuitBreaker in the HalfOpen state, recording a failure
     * SHALL transition from HalfOpen to Open.
     *
     * **Validates: Requirements 3.7, 3.8**
     *
     * @param int $failureThreshold The configured failure threshold.
     * @return void
     */
    #[Test]
    #[DataProvider('halfOpenFailureProvider')]
    public function halfOpenProbeFailureTransitionsToOpen(int $failureThreshold): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: $failureThreshold,
            cooldownMs: 1,
        );
        $breaker = new CircuitBreaker($config);

        // Drive to Open state by recording failureThreshold consecutive failures
        for ($i = 0; $i < $failureThreshold; $i++) {
            $breaker->recordFailure();
        }

        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            "Circuit should be Open after {$failureThreshold} failures"
        );

        // Wait for cooldown to elapse (1ms cooldown + 2ms sleep)
        usleep(5000);

        // isCallAllowed() should transition from Open to HalfOpen
        $allowed = $breaker->isCallAllowed();

        $this->assertTrue($allowed, 'Call should be allowed after cooldown (HalfOpen probe)');
        $this->assertSame(
            CircuitState::HalfOpen,
            $breaker->getState(),
            'Circuit should be in HalfOpen state after cooldown elapsed'
        );

        // Record failure in HalfOpen — should transition back to Open
        $breaker->recordFailure();

        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            'Circuit should transition to Open after failure in HalfOpen'
        );

        // Verify the circuit is now blocking calls (Open state, cooldown restarted)
        $this->assertFalse(
            $breaker->isCallAllowed(),
            'Call should be blocked immediately after re-opening (cooldown restarted)'
        );
    }
}
