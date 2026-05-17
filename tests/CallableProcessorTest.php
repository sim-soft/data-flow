<?php

namespace Simsoft\DataFlow\Tests;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CallableProcessor;
use Simsoft\DataFlow\Enums\Signal;

/**
 * CallableProcessorTest class.
 */
#[CoversClass(CallableProcessor::class)]
class CallableProcessorTest extends TestCase
{
    #[Test]
    public function validCallableConstructsWithoutError(): void
    {
        $processor = new CallableProcessor(function (&$data) {
            return $data;
        });

        $this->assertInstanceOf(CallableProcessor::class, $processor);
    }

    #[Test]
    public function invocationWithDataframeProcessesEachItem(): void
    {
        $processor = new CallableProcessor(function (&$data) {
            return $data * 2;
        });

        $dataFrame = new ArrayIterator([1, 2, 3]);
        $result = $this->iteratorToArray($processor($dataFrame));

        $this->assertSame([2, 4, 6], $result);
    }

    #[Test]
    public function callbackReturnsTransformedValue(): void
    {
        $processor = new CallableProcessor(function (&$data) {
            return strtoupper($data);
        });

        $dataFrame = new ArrayIterator(['hello', 'world']);
        $result = $this->iteratorToArray($processor($dataFrame));

        $this->assertSame(['HELLO', 'WORLD'], $result);
    }

    #[Test]
    public function signalNextSkipsCurrentItem(): void
    {
        $processor = new CallableProcessor(function (&$data) {
            if ($data === 2) {
                return Signal::Next;
            }
            return $data;
        });

        $dataFrame = new ArrayIterator([1, 2, 3]);
        $result = $this->iteratorToArray($processor($dataFrame));

        $this->assertSame([0 => 1, 2 => 3], $result);
    }

    #[Test]
    public function signalStopHaltsIteration(): void
    {
        $processor = new CallableProcessor(function (&$data) {
            if ($data === 2) {
                return Signal::Stop;
            }
            return $data;
        });

        $dataFrame = new ArrayIterator([1, 2, 3]);
        $result = $this->iteratorToArray($processor($dataFrame));

        $this->assertSame([0 => 1], $result);
    }
}
