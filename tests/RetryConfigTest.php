<?php

namespace Simsoft\DataFlow\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\RetryConfig;

/**
 * RetryConfig value object test class.
 */
#[CoversClass(RetryConfig::class)]
class RetryConfigTest extends TestCase
{
    #[Test]
    public function defaultsToThreeAttemptsAnd100MsBackoff(): void
    {
        $config = new RetryConfig();

        $this->assertSame(3, $config->maxAttempts);
        $this->assertSame(100, $config->delay);
    }

    #[Test]
    public function acceptsCustomValues(): void
    {
        $config = new RetryConfig(maxAttempts: 5, delay: 250);

        $this->assertSame(5, $config->maxAttempts);
        $this->assertSame(250, $config->delay);
    }

    #[Test]
    public function acceptsMinimumValidValues(): void
    {
        $config = new RetryConfig(maxAttempts: 1, delay: 0);

        $this->assertSame(1, $config->maxAttempts);
        $this->assertSame(0, $config->delay);
    }

    #[Test]
    public function rejectsMaxAttemptsLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxAttempts must be >= 1');

        new RetryConfig(maxAttempts: 0);
    }

    #[Test]
    public function rejectsNegativeMaxAttempts(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxAttempts must be >= 1');

        new RetryConfig(maxAttempts: -1);
    }

    #[Test]
    public function rejectsNegativeDelay(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('delay must be >= 0');

        new RetryConfig(delay: -1);
    }

    #[Test]
    public function defaultsToExponentialTrueAndMaxDelay30000(): void
    {
        $config = new RetryConfig();

        $this->assertTrue($config->exponential);
        $this->assertSame(30000, $config->maxDelay);
    }

    #[Test]
    public function acceptsCustomExponentialAndMaxDelay(): void
    {
        $config = new RetryConfig(exponential: false, maxDelay: 60000);

        $this->assertFalse($config->exponential);
        $this->assertSame(60000, $config->maxDelay);
    }

    #[Test]
    public function rejectsMaxDelayLessThanOne(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxDelay must be >= 1');

        new RetryConfig(maxDelay: 0);
    }

    #[Test]
    public function computeDelayExponentialMode(): void
    {
        $config = new RetryConfig(delay: 100, exponential: true, maxDelay: 30000);

        $this->assertSame(100, $config->computeDelay(1));   // 100 * 2^0
        $this->assertSame(200, $config->computeDelay(2));   // 100 * 2^1
        $this->assertSame(400, $config->computeDelay(3));   // 100 * 2^2
        $this->assertSame(800, $config->computeDelay(4));   // 100 * 2^3
        $this->assertSame(1600, $config->computeDelay(5));  // 100 * 2^4
    }

    #[Test]
    public function computeDelayClampedToMaxDelay(): void
    {
        $config = new RetryConfig(delay: 1000, exponential: true, maxDelay: 5000);

        $this->assertSame(1000, $config->computeDelay(1));  // 1000 * 2^0 = 1000
        $this->assertSame(2000, $config->computeDelay(2));  // 1000 * 2^1 = 2000
        $this->assertSame(4000, $config->computeDelay(3));  // 1000 * 2^2 = 4000
        $this->assertSame(5000, $config->computeDelay(4));  // 1000 * 2^3 = 8000 → clamped to 5000
        $this->assertSame(5000, $config->computeDelay(5));  // 1000 * 2^4 = 16000 → clamped to 5000
    }

    #[Test]
    public function computeDelayLinearModeReturnsConstant(): void
    {
        $config = new RetryConfig(delay: 500, exponential: false);

        $this->assertSame(500, $config->computeDelay(1));
        $this->assertSame(500, $config->computeDelay(2));
        $this->assertSame(500, $config->computeDelay(3));
        $this->assertSame(500, $config->computeDelay(10));
    }

    #[Test]
    public function applyJitterExponentialModeWithinBounds(): void
    {
        $config = new RetryConfig(delay: 1000, exponential: true, maxDelay: 30000);
        $delayMs = 1000;

        for ($i = 0; $i < 50; $i++) {
            $jittered = $config->applyJitter($delayMs);
            $this->assertGreaterThanOrEqual((int)round($delayMs * 0.75), $jittered);
            $this->assertLessThanOrEqual((int)round($delayMs * 1.25), $jittered);
        }
    }

    #[Test]
    public function applyJitterLinearModeReturnsUnchanged(): void
    {
        $config = new RetryConfig(delay: 500, exponential: false);

        $this->assertSame(500, $config->applyJitter(500));
        $this->assertSame(1000, $config->applyJitter(1000));
        $this->assertSame(200, $config->applyJitter(200));
    }

    #[Test]
    public function applyJitterClampsToMaxDelay(): void
    {
        $config = new RetryConfig(delay: 100, exponential: true, maxDelay: 1000);

        // With a delay near maxDelay, jitter should never exceed maxDelay
        for ($i = 0; $i < 50; $i++) {
            $jittered = $config->applyJitter(950);
            $this->assertLessThanOrEqual(1000, $jittered);
            $this->assertGreaterThanOrEqual(1, $jittered);
        }
    }

    #[Test]
    public function applyJitterAlwaysReturnsAtLeastOne(): void
    {
        $config = new RetryConfig(delay: 1, exponential: true, maxDelay: 30000);

        for ($i = 0; $i < 50; $i++) {
            $jittered = $config->applyJitter(1);
            $this->assertGreaterThanOrEqual(1, $jittered);
        }
    }
}
