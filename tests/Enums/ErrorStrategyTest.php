<?php

namespace Simsoft\DataFlow\Tests\Enums;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * ErrorStrategy enum test class.
 */
#[CoversClass(ErrorStrategy::class)]
class ErrorStrategyTest extends TestCase
{
    #[Test]
    public function hasExactlyFourCases(): void
    {
        $cases = ErrorStrategy::cases();

        $this->assertCount(4, $cases);
    }

    #[Test]
    public function throwCaseHasCorrectValue(): void
    {
        $this->assertSame('throw', ErrorStrategy::Throw->value);
    }

    #[Test]
    public function skipCaseHasCorrectValue(): void
    {
        $this->assertSame('skip', ErrorStrategy::Skip->value);
    }

    #[Test]
    public function retryCaseHasCorrectValue(): void
    {
        $this->assertSame('retry', ErrorStrategy::Retry->value);
    }

    #[Test]
    public function logAndContinueCaseHasCorrectValue(): void
    {
        $this->assertSame('log-and-continue', ErrorStrategy::LogAndContinue->value);
    }
}
