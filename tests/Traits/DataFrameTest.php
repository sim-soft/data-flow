<?php

namespace Simsoft\DataFlow\Tests\Traits;

use ArrayIterator;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Traits\DataFrame;

/**
 * DataFrameTest class
 *
 * Tests for the DataFrame trait using an anonymous class.
 */
class DataFrameTest extends TestCase
{
    /**
     * Create an anonymous class instance that uses the DataFrame trait.
     *
     * @return object An instance using the DataFrame trait.
     */
    private function createDataFrameInstance(): object
    {
        return new class {
            use DataFrame;
        };
    }

    #[Test]
    public function setDataFrameWithIteratorReturnsSameViaGetDataFrame(): void
    {
        $instance = $this->createDataFrameInstance();
        $iterator = new ArrayIterator([1, 2, 3]);

        $instance->setDataFrame($iterator);

        $this->assertSame($iterator, $instance->getDataFrame());
    }

    #[Test]
    public function setDataFrameWithNullReturnsNullViaGetDataFrame(): void
    {
        $instance = $this->createDataFrameInstance();

        $instance->setDataFrame(null);

        $this->assertNull($instance->getDataFrame());
    }

    #[Test]
    public function setDataFrameReturnsInstanceForFluentChaining(): void
    {
        $instance = $this->createDataFrameInstance();
        $iterator = new ArrayIterator([1, 2, 3]);

        $result = $instance->setDataFrame($iterator);

        $this->assertSame($instance, $result);
    }
}
