<?php

namespace Simsoft\DataFlow\Tests\Properties;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * LinearDelayPropertyTest
 *
 * Feature: enterprise-resilience, Property 2: Linear delay is constant
 *
 * For any valid RetryConfig with exponential=false, and for any attempt number >= 1,
 * computeDelay(attempt) SHALL always return exactly base_delay.
 *
 * **Validates: Requirements 1.3**
 */
#[CoversClass(RetryConfig::class)]
class LinearDelayPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Property 2: Linear delay is constant
     *
     * For any valid base delay (0-10000) and any attempt number (1-100),
     * when exponential=false, computeDelay always returns the constant base delay
     * regardless of attempt number.
     *
     * **Validates: Requirements 1.3**
     */
    #[Test]
    public function linearDelayIsConstantForAnyAttempt(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $baseDelay = random_int(0, 10000);
            $attempt = random_int(1, 100);

            $config = new RetryConfig(
                maxAttempts: 100,
                delay: $baseDelay,
                exponential: false,
                maxDelay: 30000,
            );

            $result = $config->computeDelay($attempt);

            $this->assertSame(
                $baseDelay,
                $result,
                "Linear delay must equal base_delay ({$baseDelay}) for attempt {$attempt}, got {$result} (iteration {$i})"
            );
        }
    }

    /**
     * Property 2 (extended): Linear delay is constant across all attempts for a given config
     *
     * For any valid RetryConfig with exponential=false, calling computeDelay with
     * different attempt numbers SHALL always return the same base_delay value.
     *
     * **Validates: Requirements 1.3**
     */
    #[Test]
    public function linearDelayIsIdenticalAcrossMultipleAttempts(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $baseDelay = random_int(0, 10000);

            $config = new RetryConfig(
                maxAttempts: 100,
                delay: $baseDelay,
                exponential: false,
                maxDelay: 30000,
            );

            // Pick two different random attempt numbers
            $attempt1 = random_int(1, 50);
            $attempt2 = random_int(51, 100);

            $result1 = $config->computeDelay($attempt1);
            $result2 = $config->computeDelay($attempt2);

            $this->assertSame(
                $result1,
                $result2,
                "Linear delay must be identical across attempts: "
                . "attempt {$attempt1} returned {$result1}, attempt {$attempt2} returned {$result2} "
                . "(base_delay={$baseDelay}, iteration {$i})"
            );
        }
    }
}
