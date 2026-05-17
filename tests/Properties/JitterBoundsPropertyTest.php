<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * JitterBoundsPropertyTest class
 *
 * Feature: enterprise-resilience, Property 3: Jitter bounds invariant
 *
 * For any computed delay value and for any application of jitter, the resulting
 * jittered delay SHALL be within the range [computedDelay × 0.75, min(computedDelay × 1.25, maxDelay)].
 *
 * **Validates: Requirements 2.1, 2.2, 2.3**
 */
#[CoversClass(RetryConfig::class)]
class JitterBoundsPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random delay/maxDelay combinations for jitter bounds testing.
     *
     * Each case provides a random delay (1-30000) and a random maxDelay that is >= delay,
     * ensuring valid RetryConfig construction.
     *
     * @return Generator
     */
    public static function jitterBoundsProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $delay = random_int(1, 30000);
            $maxDelay = random_int($delay, 60000);

            yield "delay={$delay},maxDelay={$maxDelay},i={$i}" => [
                $delay,
                $maxDelay,
            ];
        }
    }

    /**
     * Property 3: Jitter bounds invariant — result is within ±25% of input delay.
     *
     * For any valid delay with exponential=true, applyJitter result is always
     * within [delay * 0.75, delay * 1.25].
     *
     * **Validates: Requirements 2.1, 2.2**
     */
    #[Test]
    #[DataProvider('jitterBoundsProvider')]
    public function jitterResultIsWithinTwentyFivePercentBounds(int $delay, int $maxDelay): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            delay: 100,
            exponential: true,
            maxDelay: $maxDelay,
        );

        // Run multiple iterations per input to exercise the random distribution
        for ($j = 0; $j < 10; $j++) {
            $result = $config->applyJitter($delay);

            $lowerBound = (int)round($delay * 0.75);
            $upperBound = (int)round($delay * 1.25);

            $this->assertGreaterThanOrEqual(
                $lowerBound,
                $result,
                "Jitter result {$result} is below lower bound {$lowerBound} "
                . "(delay={$delay}, maxDelay={$maxDelay})"
            );

            $this->assertLessThanOrEqual(
                $upperBound,
                $result,
                "Jitter result {$result} is above upper bound {$upperBound} "
                . "(delay={$delay}, maxDelay={$maxDelay})"
            );
        }
    }

    /**
     * Property 3: Jitter bounds invariant — result is always >= 1.
     *
     * For any valid delay with exponential=true, applyJitter result is always >= 1ms.
     *
     * **Validates: Requirements 2.1, 2.2**
     */
    #[Test]
    #[DataProvider('jitterBoundsProvider')]
    public function jitterResultIsAlwaysAtLeastOne(int $delay, int $maxDelay): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            delay: 100,
            exponential: true,
            maxDelay: $maxDelay,
        );

        for ($j = 0; $j < 10; $j++) {
            $result = $config->applyJitter($delay);

            $this->assertGreaterThanOrEqual(
                1,
                $result,
                "Jitter result {$result} is less than 1 (delay={$delay}, maxDelay={$maxDelay})"
            );
        }
    }

    /**
     * Property 3: Jitter bounds invariant — result never exceeds maxDelay.
     *
     * For any valid delay with exponential=true, applyJitter result never exceeds maxDelay.
     *
     * **Validates: Requirements 2.3**
     */
    #[Test]
    #[DataProvider('jitterBoundsProvider')]
    public function jitterResultNeverExceedsMaxDelay(int $delay, int $maxDelay): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            delay: 100,
            exponential: true,
            maxDelay: $maxDelay,
        );

        for ($j = 0; $j < 10; $j++) {
            $result = $config->applyJitter($delay);

            $this->assertLessThanOrEqual(
                $maxDelay,
                $result,
                "Jitter result {$result} exceeds maxDelay {$maxDelay} "
                . "(delay={$delay})"
            );
        }
    }
}
