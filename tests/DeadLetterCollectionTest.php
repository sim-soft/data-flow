<?php

namespace Simsoft\DataFlow\Tests;

use Countable;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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
