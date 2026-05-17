<?php

namespace Simsoft\DataFlow\Tests;

use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\Traits\Macroable;

/**
 * ProcessorTest
 *
 * Tests for the Processor abstract base class.
 */
#[CoversClass(Processor::class)]
class ProcessorTest extends TestCase
{
    /** @var Processor The concrete stub instance for testing. */
    private Processor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new class extends Processor {
            /**
             * Invoke the processor.
             *
             * @param Iterator|null $dataFrame
             * @return Iterator
             */
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                return $dataFrame ?? new \ArrayIterator([]);
            }
        };

        // Clear macros between tests to avoid state leakage
        Processor::macro('__reset_macros', function () {
        });
        $reflection = new \ReflectionClass($this->processor);
        $property = $reflection->getProperty('macros');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    #[Test]
    public function setFlowReturnsSameInstance(): void
    {
        $flow = new DataFlow();

        $result = $this->processor->setFlow($flow);

        $this->assertSame($flow, $this->processor->getFlow());
    }

    #[Test]
    public function setFlowReturnsProcessorForFluentChaining(): void
    {
        $flow = new DataFlow();

        $result = $this->processor->setFlow($flow);

        $this->assertSame($this->processor, $result);
    }

    #[Test]
    public function processorUsesMacroableTrait(): void
    {
        $traits = class_uses(Processor::class);

        $this->assertArrayHasKey(Macroable::class, $traits);
    }
}
