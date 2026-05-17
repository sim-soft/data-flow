<?php

namespace Simsoft\DataFlow\Tests\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * DataFlowExceptionTest class.
 */
#[CoversClass(DataFlowException::class)]
class DataFlowExceptionTest extends TestCase
{
    #[Test]
    public function extendsRuntimeException(): void
    {
        $exception = new DataFlowException('test error');

        $this->assertInstanceOf(RuntimeException::class, $exception);
    }

    #[Test]
    public function getMessageReturnsProvidedMessage(): void
    {
        $message = 'Something went wrong in the pipeline';
        $exception = new DataFlowException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
