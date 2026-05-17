<?php

namespace Simsoft\DataFlow\Tests\Enums;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * Signal enum test class.
 */
#[CoversClass(Signal::class)]
class SignalTest extends TestCase
{
    #[Test]
    public function nextHasValueOne(): void
    {
        $this->assertSame(1, Signal::Next->value);
    }

    #[Test]
    public function stopHasValueNine(): void
    {
        $this->assertSame(9, Signal::Stop->value);
    }
}
