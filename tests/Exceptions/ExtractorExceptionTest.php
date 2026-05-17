<?php

namespace Simsoft\DataFlow\Tests\Exceptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Exceptions\ExtractorException;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * ExtractorExceptionTest class.
 */
#[CoversClass(ExtractorException::class)]
class ExtractorExceptionTest extends TestCase
{
    #[Test]
    public function extendsDataFlowException(): void
    {
        $exception = new ExtractorException('extractor failed');

        $this->assertInstanceOf(DataFlowException::class, $exception);
    }

    #[Test]
    public function getMessageReturnsProvidedMessage(): void
    {
        $message = 'Failed to read data source';
        $exception = new ExtractorException($message);

        $this->assertSame($message, $exception->getMessage());
    }
}
