<?php

namespace Simsoft\DataFlow\Tests;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\RetryConfig;

/**
 * ProcessorErrorStrategyTest
 *
 * Tests for Processor error strategy configuration and Loader dry-run support.
 * Validates: Requirements 1.2, 1.7, 12.3
 */
#[CoversClass(Processor::class)]
#[CoversClass(Loader::class)]
#[CoversClass(RetryConfig::class)]
class ProcessorErrorStrategyTest extends TestCase
{
    /** @var Processor Concrete test double for the abstract Processor. */
    private Processor $processor;

    /** @var Loader Concrete test double for the abstract Loader. */
    private Loader $loader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new class extends Processor {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                return $dataFrame ?? new \ArrayIterator([]);
            }
        };

        $this->loader = new class extends Loader {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                return $dataFrame ?? new \ArrayIterator([]);
            }
        };
    }

    #[Test]
    public function defaultErrorStrategyIsThrow(): void
    {
        $this->assertSame(ErrorStrategy::Throw, $this->processor->getErrorStrategy());
    }

    #[Test]
    public function withErrorStrategyReturnsSameInstanceForFluentChaining(): void
    {
        $result = $this->processor->withErrorStrategy(ErrorStrategy::Skip);

        $this->assertSame($this->processor, $result);
    }

    #[Test]
    public function withErrorStrategySetsTheStrategy(): void
    {
        $this->processor->withErrorStrategy(ErrorStrategy::Skip);

        $this->assertSame(ErrorStrategy::Skip, $this->processor->getErrorStrategy());
    }

    #[Test]
    public function withErrorStrategyCanSetLogAndContinue(): void
    {
        $this->processor->withErrorStrategy(ErrorStrategy::LogAndContinue);

        $this->assertSame(ErrorStrategy::LogAndContinue, $this->processor->getErrorStrategy());
    }

    #[Test]
    public function withRetrySetsStrategyToRetry(): void
    {
        $this->processor->withRetry();

        $this->assertSame(ErrorStrategy::Retry, $this->processor->getErrorStrategy());
    }

    #[Test]
    public function withRetryCreatesRetryConfig(): void
    {
        $this->processor->withRetry(5, 200);

        $retryConfig = $this->processor->getRetryConfig();

        $this->assertInstanceOf(RetryConfig::class, $retryConfig);
        $this->assertSame(5, $retryConfig->maxAttempts);
        $this->assertSame(200, $retryConfig->delay);
    }

    #[Test]
    public function withRetryUsesDefaultValues(): void
    {
        $this->processor->withRetry();

        $retryConfig = $this->processor->getRetryConfig();

        $this->assertInstanceOf(RetryConfig::class, $retryConfig);
        $this->assertSame(3, $retryConfig->maxAttempts);
        $this->assertSame(100, $retryConfig->delay);
    }

    #[Test]
    public function withRetryReturnsSameInstanceForFluentChaining(): void
    {
        $result = $this->processor->withRetry();

        $this->assertSame($this->processor, $result);
    }

    #[Test]
    public function getRetryConfigReturnsNullByDefault(): void
    {
        $this->assertNull($this->processor->getRetryConfig());
    }

    #[Test]
    public function withNameReturnsSameInstanceForFluentChaining(): void
    {
        $result = $this->processor->withName('my-processor');

        $this->assertSame($this->processor, $result);
    }

    #[Test]
    public function withNameSetsTheProcessorName(): void
    {
        $this->processor->withName('custom-name');

        $this->assertSame('custom-name', $this->processor->getName());
    }

    #[Test]
    public function getNameDefaultsToClassName(): void
    {
        $name = $this->processor->getName();

        // Anonymous classes have auto-generated class names
        $this->assertSame($this->processor::class, $name);
    }

    #[Test]
    public function loaderIsDryRunDefaultsToFalse(): void
    {
        $this->assertFalse($this->loader->isDryRun());
    }

    #[Test]
    public function loaderSetDryRunEnablesDryRunMode(): void
    {
        $this->loader->setDryRun(true);

        $this->assertTrue($this->loader->isDryRun());
    }

    #[Test]
    public function loaderSetDryRunCanDisableDryRunMode(): void
    {
        $this->loader->setDryRun(true);
        $this->loader->setDryRun(false);

        $this->assertFalse($this->loader->isDryRun());
    }

    #[Test]
    public function loaderInheritsErrorStrategyFromProcessor(): void
    {
        $this->assertSame(ErrorStrategy::Throw, $this->loader->getErrorStrategy());

        $this->loader->withErrorStrategy(ErrorStrategy::Skip);

        $this->assertSame(ErrorStrategy::Skip, $this->loader->getErrorStrategy());
    }
}
