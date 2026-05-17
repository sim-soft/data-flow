<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * LinearModeNoJitterPropertyTest class
 *
 * Feature: enterprise-resilience, Property 4: Linear mode applies no jitter
 *
 * Property-based test verifying that for any valid RetryConfig with exponential=false,
 * applyJitter always returns the input delay unchanged (identity function).
 *
 * **Validates: Requirements 2.4**
 */
#[CoversClass(RetryConfig::class)]
class LinearModeNoJitterPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random delay values for linear mode jitter tests.
     *
     * Each case provides:
     * - delay: random int in [0, 30000] (the input to applyJitter)
     * - baseDelay: random int in [0, 30000] (the config base delay)
     *
     * @return Generator
     */
    public static function linearModeDelayProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $delay = random_int(0, 30000);
            $baseDelay = random_int(0, 30000);

            yield "delay={$delay},base={$baseDelay},i={$i}" => [
                $delay,
                $baseDelay,
            ];
        }
    }

    /**
     * Property 4: Linear mode applies no jitter
     *
     * For any valid RetryConfig with exponential=false, and for any valid delay (0-30000),
     * applyJitter always returns the input delay unchanged (identity function).
     * The final delay SHALL always equal exactly the input with zero variance
     * across repeated invocations.
     *
     * **Validates: Requirements 2.4**
     */
    #[Test]
    #[DataProvider('linearModeDelayProvider')]
    public function linearModeAppliesNoJitter(int $delay, int $baseDelay): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            delay: $baseDelay,
            exponential: false,
            maxDelay: 30000,
        );

        // Apply jitter multiple times to verify zero variance
        $result1 = $config->applyJitter($delay);
        $result2 = $config->applyJitter($delay);
        $result3 = $config->applyJitter($delay);

        // applyJitter must return the input delay unchanged (identity function)
        $this->assertSame(
            $delay,
            $result1,
            "applyJitter({$delay}) should return {$delay} unchanged in linear mode, got {$result1}"
        );

        // Verify zero variance across repeated invocations
        $this->assertSame(
            $result1,
            $result2,
            "applyJitter should produce zero variance in linear mode"
        );

        $this->assertSame(
            $result1,
            $result3,
            "applyJitter should produce zero variance in linear mode"
        );
    }
}
