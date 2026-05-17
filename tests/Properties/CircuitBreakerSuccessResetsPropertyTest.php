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
 * CircuitBreakerSuccessResetsPropertyTest
 *
 * Property-based test verifying that a success in the Closed state resets
 * the consecutive failure counter.
 *
 * Feature: enterprise-resilience, Property 7: Success in Closed state resets failure counter
 */
#[CoversClass(CircuitBreaker::class)]
class CircuitBreakerSuccessResetsPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random scenarios for success-resets-counter property.
     *
     * Each case provides a random failureThreshold (2-50) and a random number of
     * failures less than the threshold, followed by a success that should reset the counter.
     *
     * @return Generator
     */
    public static function successResetsCounterProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $failureThreshold = random_int(2, 50);
            $failuresBefore = random_int(1, $failureThreshold - 1);

            yield "threshold={$failureThreshold},failures={$failuresBefore},i={$i}" => [
                $failureThreshold,
                $failuresBefore,
            ];
        }
    }

    /**
     * Property 7: Success in Closed state resets failure counter
     *
     * For any CircuitBreaker in the Closed state with K consecutive failures
     * (where K < threshold), recording a success SHALL reset the consecutive
     * failure counter to zero. After the reset, it takes another full
     * failureThreshold failures to open the circuit.
     *
     * **Validates: Requirements 3.9**
     *
     * @param int $failureThreshold The configured failure threshold.
     * @param int $failuresBefore Number of failures to record before the success (< threshold).
     * @return void
     */
    #[Test]
    #[DataProvider('successResetsCounterProvider')]
    public function successInClosedStateResetsFailureCounter(
        int $failureThreshold,
        int $failuresBefore,
    ): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: $failureThreshold,
            cooldownMs: 10000,
        );
        $breaker = new CircuitBreaker($config);

        // Record K failures (K < threshold) — circuit should remain Closed
        for ($i = 0; $i < $failuresBefore; $i++) {
            $breaker->recordFailure();
        }

        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            "Circuit should remain Closed after {$failuresBefore} failures "
            . "(threshold={$failureThreshold})"
        );

        // Record a success — this should reset the failure counter
        $breaker->recordSuccess();

        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            'Circuit should remain Closed after success resets counter'
        );

        // After the reset, it should take a full failureThreshold failures to open
        // Record (failureThreshold - 1) failures — circuit should still be Closed
        for ($i = 0; $i < $failureThreshold - 1; $i++) {
            $breaker->recordFailure();
        }

        $this->assertSame(
            CircuitState::Closed,
            $breaker->getState(),
            "Circuit should remain Closed after {$failureThreshold}-1 failures post-reset "
            . "(proving counter was fully reset)"
        );

        // The final failure (reaching threshold) should open the circuit
        $breaker->recordFailure();

        $this->assertSame(
            CircuitState::Open,
            $breaker->getState(),
            "Circuit should transition to Open after exactly {$failureThreshold} "
            . "consecutive failures post-reset"
        );
    }
}
