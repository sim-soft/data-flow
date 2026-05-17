<?php

namespace Simsoft\DataFlow\Tests\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Exceptions\LoaderException;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * LoaderExceptionTest class.
 */
#[CoversClass(LoaderException::class)]
class LoaderExceptionTest extends TestCase
{
    #[Test]
    public function extendsDataFlowException(): void
    {
        $exception = new LoaderException('loader failed');

        $this->assertInstanceOf(DataFlowException::class, $exception);
    }

    #[Test]
    public function getMessageReturnsProvidedMessage(): void
    {
        $message = 'Failed to write output';
        $exception = new LoaderException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
