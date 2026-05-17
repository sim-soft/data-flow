<?php

namespace Simsoft\DataFlow\Tests\Extractors;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Extractors\ActiveQueryExtractor;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DB\MySQL\Builder\ActiveQuery;
use Simsoft\DB\MySQL\Collection;

/**
 * ActiveQueryExtractorTest class
 *
 * Tests for the ActiveQueryExtractor class.
 */
#[CoversClass(ActiveQueryExtractor::class)]
class ActiveQueryExtractorTest extends TestCase
{
    /**
     * Create a mock ActiveQuery that returns a Collection mock from each().
     *
     * @param array $rows Rows to yield from the collection.
     * @param int|null $expectedSize Expected size argument for each().
     * @return ActiveQuery
     */
    private function createActiveQueryMock(array $rows = [], ?int $expectedSize = null): ActiveQuery
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Configure the collection mock to iterate over the provided rows
        $iterator = new ArrayIterator($rows);
        $collection->method('rewind')->willReturnCallback(fn() => $iterator->rewind());
        $collection->method('current')->willReturnCallback(fn() => $iterator->current());
        $collection->method('key')->willReturnCallback(fn() => $iterator->key());
        $collection->method('next')->willReturnCallback(fn() => $iterator->next());
        $collection->method('valid')->willReturnCallback(fn() => $iterator->valid());
        $collection->method('toArray')->willReturnSelf();
        $collection->method('debug')->willReturnSelf();

        $query = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($expectedSize !== null) {
            $query->expects($this->once())
                ->method('each')
                ->with($expectedSize)
                ->willReturn($collection);
        } else {
            $query->method('each')->willReturn($collection);
        }

        return $query;
    }

    #[Test]
    public function constructsWithActiveQueryMock(): void
    {
        $query = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();

        $extractor = new ActiveQueryExtractor($query);

        $this->assertInstanceOf(ActiveQueryExtractor::class, $extractor);
    }

    #[Test]
    public function sizeSetsTheBatchParameterPassedToEach(): void
    {
        $query = $this->createActiveQueryMock(
            rows: [['id' => 1, 'name' => 'Alice']],
            expectedSize: 50
        );

        $extractor = new ActiveQueryExtractor($query);
        $extractor->size(50);

        // Trigger invocation to verify each() is called with the correct size
        iterator_to_array($extractor());
    }

    #[Test]
    public function toArrayCallsToArrayOnCollection(): void
    {
        $collection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $collection->expects($this->once())
            ->method('toArray')
            ->willReturnSelf();

        // Configure iteration (empty)
        $collection->method('valid')->willReturn(false);

        $query = $this->getMockBuilder(ActiveQuery::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->method('each')->willReturn($collection);

        $extractor = new ActiveQueryExtractor($query);
        $extractor->toArray();

        iterator_to_array($extractor());
    }

    #[Test]
    public function makeFactoryReturnsConfiguredInstance(): void
    {
        $query = $this->createActiveQueryMock(
            rows: [['id' => 1]],
            expectedSize: 25
        );

        $extractor = ActiveQueryExtractor::make($query, 25);

        $this->assertInstanceOf(ActiveQueryExtractor::class, $extractor);

        // Invoke to verify the size was set correctly
        iterator_to_array($extractor());
    }

    #[Test]
    public function invocationYieldsAllRowsFromQueryCollection(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];

        $query = $this->createActiveQueryMock(rows: $rows);

        $extractor = new ActiveQueryExtractor($query);
        $result = $this->iteratorToArray($extractor());

        $this->assertSame($rows, $result);
    }
}
