<?php

namespace Simsoft\DataFlow\Tests;

use ArrayIterator;
use Closure;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CallableProcessor;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\Transformers\Chunk;
use Simsoft\DataFlow\Transformers\Filter;
use Simsoft\DataFlow\Transformers\Mapping;

/**
 * DataFlowTest class.
 *
 * Tests for the DataFlow pipeline orchestration.
 */
#[CoversClass(DataFlow::class)]
class DataFlowTest extends TestCase
{
    #[Test]
    public function constructorInitializesWithNullDataFrame(): void
    {
        $flow = new DataFlow();
        $this->assertNull($flow->getDataFrame());
    }

    #[Test]
    public function fromWithArrayWrapsInIterableExtractor(): void
    {
        $data = [1, 2, 3];
        $flow = new DataFlow();
        $flow->from($data);

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame($data, $result);
    }

    #[Test]
    public function fromWithClosureWrapsInCallableProcessor(): void
    {
        $flow = new DataFlow();
        $flow->from([10, 20, 30]);
        $flow->from(function (mixed $data) {
            return $data * 2;
        });

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame([20, 40, 60], $result);
    }

    #[Test]
    public function fromWithDataFlowAdoptsSourceDataFrame(): void
    {
        $source = new DataFlow();
        $source->from([100, 200, 300]);

        $flow = new DataFlow();
        $flow->from($source);

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame([100, 200, 300], $result);
    }

    #[Test]
    public function transformWithClosureTransformsEachItem(): void
    {
        $flow = new DataFlow();
        $flow->from([1, 2, 3]);
        $flow->transform(function (mixed $data) {
            return $data + 10;
        });

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame([11, 12, 13], $result);
    }

    #[Test]
    public function transformWithProcessorUsesInvoke(): void
    {
        $processor = new CallableProcessor(function (mixed $data) {
            return $data * 3;
        });

        $flow = new DataFlow();
        $flow->from([2, 4, 6]);
        $flow->transform($processor);

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame([6, 12, 18], $result);
    }

    #[Test]
    public function filterYieldsOnlyMatchingItems(): void
    {
        $flow = new DataFlow();
        $flow->from([1, 2, 3, 4, 5]);
        $flow->filter(function (mixed $data) {
            return $data > 3;
        });

        $result = $this->iteratorToArray($flow->getDataFrame());
        $this->assertSame([3 => 4, 4 => 5], $result);
    }

    #[Test]
    public function mapRemapsArrayKeys(): void
    {
        $data = [
            ['first_name' => 'Alice', 'last_name' => 'Smith'],
            ['first_name' => 'Bob', 'last_name' => 'Jones'],
        ];

        $flow = new DataFlow();
        $flow->from($data);
        $flow->map(['name' => 'first_name', 'surname' => 'last_name']);

        $result = $this->iteratorToArray($flow->getDataFrame());

        $this->assertSame('Alice', $result[0]['name']);
        $this->assertSame('Smith', $result[0]['surname']);
        $this->assertSame('Bob', $result[1]['name']);
        $this->assertSame('Jones', $result[1]['surname']);
    }

    #[Test]
    public function setNewMapCreatesDataFrameWithOnlyMappedKeys(): void
    {
        $data = [
            ['first_name' => 'Alice', 'last_name' => 'Smith', 'age' => 30],
        ];

        $flow = new DataFlow();
        $flow->from($data);
        $flow->setNewMap(['name' => 'first_name']);

        $result = $this->iteratorToArray($flow->getDataFrame());

        $this->assertSame(['name' => 'Alice'], $result[0]);
    }

    #[Test]
    public function chunkBatchesItemsIntoArrays(): void
    {
        $flow = new DataFlow();
        $flow->from([1, 2, 3, 4, 5]);
        $flow->chunk(2);

        $result = $this->iteratorToArray($flow->getDataFrame());

        $this->assertSame([[1, 2], [3, 4], [5]], $result);
    }

    #[Test]
    public function limitStopsAfterSpecifiedCount(): void
    {
        $flow = new DataFlow();
        $flow->from([10, 20, 30, 40, 50]);
        $flow->limit(3);

        $result = $this->iteratorToArray($flow->getDataFrame());

        $this->assertCount(3, $result);
        $this->assertSame([10, 20, 30], $result);
    }

    #[Test]
    public function loadPassesItemsThroughLoaderClosure(): void
    {
        $collected = [];

        $flow = new DataFlow();
        $flow->from([1, 2, 3]);
        $flow->load(function (mixed $data) use (&$collected) {
            $collected[] = $data;
            return $data;
        });

        // Consume the iterator to trigger the loader
        $this->iteratorToArray($flow->getDataFrame());

        $this->assertSame([1, 2, 3], $collected);
    }

    #[Test]
    public function previewThrowsExceptionWhenMaxIsZero(): void
    {
        $this->expectException(DataFlowException::class);
        $this->expectExceptionMessage('Max number of previews must be greater than 0');

        $flow = new DataFlow();
        $flow->from([1, 2, 3]);
        $flow->preview(0);
    }

    #[Test]
    public function previewThrowsExceptionWhenMaxIsNegative(): void
    {
        $this->expectException(DataFlowException::class);
        $this->expectExceptionMessage('Max number of previews must be greater than 0');

        $flow = new DataFlow();
        $flow->from([1, 2, 3]);
        $flow->preview(-1);
    }

    #[Test]
    public function runExecutesAllStagesAndProducesExpectedOutput(): void
    {
        $collected = [];

        $flow = new DataFlow();
        $flow->from([1, 2, 3, 4, 5]);
        $flow->transform(function (mixed $data) {
            return $data * 2;
        });
        $flow->filter(function (mixed $data) {
            return $data > 4;
        });
        $flow->load(function (mixed $data) use (&$collected) {
            $collected[] = $data;
            return $data;
        });
        $flow->run();

        $this->assertSame([6, 8, 10], $collected);
    }

    #[Test]
    public function fromWithMultipleExtractorsChainsSequentially(): void
    {
        $flow = new DataFlow();
        $flow->from([1, 2], [3, 4]);

        $result = $this->iteratorToArray($flow->getDataFrame());

        $this->assertSame([3, 4], $result);
    }

    #[Test]
    public function transformWithMultipleTransformersAppliesInOrder(): void
    {
        $flow = new DataFlow();
        $flow->from([1, 2, 3]);
        $flow->transform(
            function (mixed $data) {
                return $data + 10;
            },
            function (mixed $data) {
                return $data * 2;
            }
        );

        $result = $this->iteratorToArray($flow->getDataFrame());

        // First: +10 → [11, 12, 13], then *2 → [22, 24, 26]
        $this->assertSame([22, 24, 26], $result);
    }
}
