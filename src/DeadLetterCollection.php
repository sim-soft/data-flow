<?php

namespace Simsoft\DataFlow;

use ArrayIterator;
use Countable;
use IteratorAggregate;

/**
 * DeadLetterCollection
 *
 * A collection of rows that failed processing in the pipeline.
 * Provides iterable and countable access to dead-letter entries
 * for inspection and potential reprocessing.
 *
 * @implements IteratorAggregate<int, DeadLetterEntry>
 */
final class DeadLetterCollection implements Countable, IteratorAggregate
{
    /** @var DeadLetterEntry[] */
    private array $entries = [];

    /**
     * Add a dead-letter entry to the collection.
     *
     * @param DeadLetterEntry $entry The failed row entry to add.
     *
     * @return void
     */
    public function add(DeadLetterEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * Return the number of entries in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Return an iterator over the entries.
     *
     * @return ArrayIterator<int, DeadLetterEntry>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->entries);
    }

    /**
     * Return all entries as an array.
     *
     * @return DeadLetterEntry[]
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
