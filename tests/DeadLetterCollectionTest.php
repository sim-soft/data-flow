<?php

namespace Simsoft\DataFlow\Tests;

use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\DeadLetterEntry;

/**
 * DeadLetterCollection test class.
 */
#[CoversClass(DeadLetterCollection::class)]
class DeadLetterCollectionTest extends TestCase
{
    #[Test]
    public function implementsCountable(): void
    {
        $collection = new DeadLetterCollection();

        $this->assertInstanceOf(Countable::class, $collection);
    }

    #[Test]
    public function implementsIteratorAggregate(): void
    {
        $collection = new DeadLetterCollection();

        $this->assertInstanceOf(IteratorAggregate::class, $collection);
    }

    #[Test]
    public function emptyCollectionHasCountZero(): void
    {
        $collection = new DeadLetterCollection();

        $this->assertCount(0, $collection);
    }

    #[Test]
    public function countReflectsAddedEntries(): void
    {
        $collection = new DeadLetterCollection();
        $collection->add($this->createEntry('stage-1', 0));
        $collection->add($this->createEntry('stage-2', 1));

        $this->assertCount(2, $collection);
    }

    #[Test]
    public function isIterableWithForeach(): void
    {
        $collection = new DeadLetterCollection();
        $entry1 = $this->createEntry('stage-a', 0);
        $entry2 = $this->createEntry('stage-b', 1);
        $collection->add($entry1);
        $collection->add($entry2);

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        $this->assertCount(2, $items);
        $this->assertSame($entry1, $items[0]);
        $this->assertSame($entry2, $items[1]);
    }

    #[Test]
    public function toArrayReturnsAllEntries(): void
    {
        $collection = new DeadLetterCollection();
        $entry = $this->createEntry('stage-x', 5);
        $collection->add($entry);

        $array = $collection->toArray();

        $this->assertIsArray($array);
        $this->assertCount(1, $array);
        $this->assertSame($entry, $array[0]);
    }

    #[Test]
    public function retainsEntriesUpToTheLimitAndDiscardsTheRest(): void
    {
        $collection = new DeadLetterCollection(3);

        for ($i = 0; $i < 10; $i++) {
            $collection->add($this->createEntry('stage', $i));
        }

        $this->assertCount(3, $collection);
        $this->assertSame(10, $collection->totalCount());
        $this->assertSame(7, $collection->droppedCount());
        $this->assertTrue($collection->isTruncated());
    }

    #[Test]
    public function retainsTheEarliestEntriesWhenTruncating(): void
    {
        $collection = new DeadLetterCollection(2);

        for ($i = 0; $i < 5; $i++) {
            $collection->add($this->createEntry('stage', $i));
        }

        // The first failure usually explains the ones that follow, so the cap
        // keeps the head of the sequence rather than the tail.
        $indexes = array_map(
            static fn(DeadLetterEntry $entry): int => $entry->rowIndex,
            $collection->toArray()
        );

        $this->assertSame([0, 1], $indexes);
    }

    #[Test]
    public function isNotTruncatedWhenEntriesFitWithinTheLimit(): void
    {
        $collection = new DeadLetterCollection(5);
        $collection->add($this->createEntry('stage', 0));
        $collection->add($this->createEntry('stage', 1));

        $this->assertCount(2, $collection);
        $this->assertSame(2, $collection->totalCount());
        $this->assertSame(0, $collection->droppedCount());
        $this->assertFalse($collection->isTruncated());
    }

    #[Test]
    public function nullLimitRetainsEveryEntry(): void
    {
        $collection = new DeadLetterCollection(null);

        for ($i = 0; $i < 50; $i++) {
            $collection->add($this->createEntry('stage', $i));
        }

        $this->assertCount(50, $collection);
        $this->assertSame(50, $collection->totalCount());
        $this->assertFalse($collection->isTruncated());
        $this->assertNull($collection->getLimit());
    }

    #[Test]
    public function defaultsToTheDefaultLimit(): void
    {
        $collection = new DeadLetterCollection();

        $this->assertSame(DeadLetterCollection::DEFAULT_LIMIT, $collection->getLimit());
    }

    #[Test]
    public function iterationYieldsOnlyRetainedEntries(): void
    {
        $collection = new DeadLetterCollection(2);

        for ($i = 0; $i < 6; $i++) {
            $collection->add($this->createEntry('stage', $i));
        }

        $items = [];
        foreach ($collection as $item) {
            $items[] = $item;
        }

        $this->assertCount(2, $items);
    }

    #[Test]
    public function emptyCollectionIsNotTruncated(): void
    {
        $collection = new DeadLetterCollection(10);

        $this->assertSame(0, $collection->totalCount());
        $this->assertSame(0, $collection->droppedCount());
        $this->assertFalse($collection->isTruncated());
    }

    #[Test]
    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([-100])]
    public function rejectsANonPositiveLimit(int $limit): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DeadLetterCollection($limit);
    }

    private function createEntry(string $stageName, int $rowIndex): DeadLetterEntry
    {
        return new DeadLetterEntry(
            row: ['id' => $rowIndex],
            stageName: $stageName,
            rowIndex: $rowIndex,
            exception: new \RuntimeException("Test error at $stageName"),
        );
    }
}
