<?php

namespace Simsoft\DataFlow\Tests\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Exceptions\TransformerException;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * TransformerExceptionTest class.
 */
#[CoversClass(TransformerException::class)]
class TransformerExceptionTest extends TestCase
{
    #[Test]
    public function extendsDataFlowException(): void
    {
        $exception = new TransformerException('transformer failed');

        $this->assertInstanceOf(DataFlowException::class, $exception);
    }

    #[Test]
    public function getMessageReturnsProvidedMessage(): void
    {
        $message = 'Failed to transform data';
        $exception = new TransformerException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
