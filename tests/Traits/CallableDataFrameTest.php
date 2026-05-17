<?php

namespace Simsoft\DataFlow\Tests\Traits;

use ArrayIterator;
use Iterator;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Traits\CallableDataFrame;

/**
 * CallableDataFrameTest class
 *
 * Tests for the CallableDataFrame trait using an anonymous class.
 */
class CallableDataFrameTest extends TestCase
{
    /**
     * Create an anonymous class instance that uses the CallableDataFrame trait.
     *
     * @return object An instance using the CallableDataFrame trait.
     */
    private function createCallableDataFrameInstance(): object
    {
        return new class {
            use CallableDataFrame;
        };
    }

    #[Test]
    public function callWithTransformedDataOutputYieldsTransformedItems(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator(['hello', 'world']);

        $result = $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                return strtoupper($data);
            })
        );

        $this->assertSame([0 => 'HELLO', 1 => 'WORLD'], $result);
    }

    #[Test]
    public function callWithSignalNextSkipsCurrentItem(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                if ($data % 2 === 0) {
                    return Signal::Next;
                }
                return $data;
            })
        );

        $this->assertSame([0 => 1, 2 => 3, 4 => 5], $result);
    }

    #[Test]
    public function callWithSignalStopHaltsIteration(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                if ($data === 3) {
                    return Signal::Stop;
                }
                return $data;
            })
        );

        $this->assertSame([0 => 1, 1 => 2], $result);
    }

    #[Test]
    public function callWithIteratorReturnYieldsViaYieldFrom(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator(['a']);

        $result = $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                return new ArrayIterator(['x', 'y', 'z']);
            })
        );

        $this->assertSame([0 => 'x', 1 => 'y', 2 => 'z'], $result);
    }

    #[Test]
    public function callWithNullReturnYieldsOriginalData(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator(['alpha', 'beta']);

        $result = $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                return null;
            })
        );

        $this->assertSame([0 => 'alpha', 1 => 'beta'], $result);
    }

    #[Test]
    public function callWithErrorCallbackThrowsDataFlowException(): void
    {
        $instance = $this->createCallableDataFrameInstance();
        $dataFrame = new ArrayIterator(['test']);

        $this->expectException(DataFlowException::class);
        $this->expectExceptionMessage('Something went wrong');

        $this->iteratorToArray(
            $instance->call($dataFrame, function (&$data, &$key, $error) {
                $error('Something went wrong');
            })
        );
    }

    #[Test]
    public function callWithNullDataFrameYieldsNothing(): void
    {
        $instance = $this->createCallableDataFrameInstance();

        $result = $this->iteratorToArray(
            $instance->call(null, function (&$data, &$key, $error) {
                return $data;
            })
        );

        $this->assertSame([], $result);
    }
}
