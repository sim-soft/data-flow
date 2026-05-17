<?php

namespace Simsoft\DataFlow\Tests\Exceptions;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\InvalidCallableException;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * InvalidCallableExceptionTest class.
 */
#[CoversClass(InvalidCallableException::class)]
class InvalidCallableExceptionTest extends TestCase
{
    #[Test]
    public function extendsInvalidArgumentException(): void
    {
        $exception = new InvalidCallableException('not callable');

        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
    }

    #[Test]
    public function getMessageReturnsProvidedMessage(): void
    {
        $message = 'Expected a valid callable';
        $exception = new InvalidCallableException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
