<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * RetryDelayPropertyTest class
 *
 * Feature: enterprise-resilience, Property 1: Exponential delay computation with clamping
 *
 * Property-based test verifying that for any valid RetryConfig with exponential=true,
 * computeDelay(attempt) returns min(base_delay × 2^(attempt-1), maxDelay).
 *
 * **Validates: Requirements 1.1, 1.5**
 */
#[CoversClass(RetryConfig::class)]
class RetryDelayPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random configurations for exponential delay tests.
     *
     * Each case provides:
     * - baseDelay: random int in [1, 10000]
     * - maxDelay: random int in [1, 60000]
     * - attempt: random int in [1, 20]
     *
     * @return Generator
     */
    public static function exponentialDelayProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $baseDelay = random_int(1, 10000);
            $maxDelay = random_int(1, 60000);
            $attempt = random_int(1, 20);

            yield "base={$baseDelay},max={$maxDelay},attempt={$attempt},i={$i}" => [
                $baseDelay,
                $maxDelay,
                $attempt,
            ];
        }
    }

    /**
     * Property 1: Exponential delay computation with clamping
     *
     * For any valid RetryConfig with exponential=true, and for any attempt number >= 1,
     * computeDelay(attempt) SHALL return min(base_delay × 2^(attempt-1), maxDelay).
     *
     * When the unclamped value <= maxDelay, the result equals base_delay × 2^(attempt-1).
     * When the unclamped value > maxDelay, the result equals exactly maxDelay.
     * The result is always >= 0 and <= maxDelay.
     *
     * **Validates: Requirements 1.1, 1.5**
     */
    #[Test]
    #[DataProvider('exponentialDelayProvider')]
    public function exponentialDelayComputationWithClamping(
        int $baseDelay,
        int $maxDelay,
        int $attempt,
    ): void
    {
        $config = new RetryConfig(
            maxAttempts: 20,
            delay: $baseDelay,
            exponential: true,
            maxDelay: $maxDelay,
        );

        $result = $config->computeDelay($attempt);

        // Compute the expected unclamped value
        $unclamped = (int)($baseDelay * (2 ** ($attempt - 1)));

        if ($unclamped <= $maxDelay) {
            // When unclamped value fits within maxDelay, result should equal the exponential formula
            $this->assertSame(
                $unclamped,
                $result,
                "Expected delay={$unclamped} (base={$baseDelay} × 2^({$attempt}-1)) "
                . "but got {$result} (maxDelay={$maxDelay})"
            );
        } else {
            // When unclamped value exceeds maxDelay, result should be clamped to maxDelay
            $this->assertSame(
                $maxDelay,
                $result,
                "Expected delay clamped to maxDelay={$maxDelay} "
                . "but got {$result} (unclamped={$unclamped}, attempt={$attempt})"
            );
        }

        // Result is always non-negative
        $this->assertGreaterThanOrEqual(
            0,
            $result,
            "Delay must be >= 0, got {$result}"
        );

        // Result never exceeds maxDelay
        $this->assertLessThanOrEqual(
            $maxDelay,
            $result,
            "Delay must be <= maxDelay ({$maxDelay}), got {$result}"
        );
    }
}
