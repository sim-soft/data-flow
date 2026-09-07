<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * DeadLetterCollection
 *
 * A collection of rows that failed processing in the pipeline.
 * Provides iterable and countable access to dead-letter entries
 * for inspection and potential reprocessing.
 *
 * Retention is capped. Each entry holds the failed row, the exception, and its
 * stack trace — roughly 13 KB — so an uncapped collection grows without bound
 * and can exhaust memory in exactly the long-running pipeline ErrorStrategy::Skip
 * exists to keep alive. Once the limit is reached, further entries are counted
 * but not stored.
 *
 * Two counts are therefore distinct, and the difference matters:
 *
 * - {@see count()} is what you can iterate — the retained entries.
 * - {@see totalCount()} is how many rows actually failed, capped or not.
 *
 * Failure totals and metrics are derived from totalCount(), so they stay exact
 * regardless of the limit. Use {@see isTruncated()} to tell whether the entries
 * you are looking at are the whole story.
 *
 * @implements IteratorAggregate<int, DeadLetterEntry>
 */
final class DeadLetterCollection implements Countable, IteratorAggregate
{
    /** @var int Retained entries when no limit is given. */
    public const int DEFAULT_LIMIT = 1000;

    /** @var DeadLetterEntry[] Retained entries; never longer than $limit. */
    private array $entries = [];

    /** @var int Every failure added, including those dropped by the cap. */
    private int $totalCount = 0;

    /**
     * Constructor.
     *
     * @param int|null $limit Maximum entries to retain. Null retains every
     *                        entry, which is unbounded — use it only when the
     *                        failure count is known to be small. Must be
     *                        positive when given.
     *
     * @throws InvalidArgumentException If the limit is zero or negative.
     */
    public function __construct(private readonly ?int $limit = self::DEFAULT_LIMIT)
    {
        if ($limit !== null && $limit < 1) {
            throw new InvalidArgumentException(
                "Dead-letter limit must be a positive integer or null for unlimited, got {$limit}."
            );
        }
    }

    /**
     * Add a dead-letter entry to the collection.
     *
     * Entries beyond the limit are counted but discarded, so memory stays
     * bounded while the failure total stays accurate. The retained entries are
     * the *first* failures rather than the last: the earliest failure in a run
     * is usually the one that explains the rest.
     *
     * @param DeadLetterEntry $entry The failed row entry to add.
     *
     * @return void
     */
    public function add(DeadLetterEntry $entry): void
    {
        $this->totalCount++;

        if ($this->limit === null || count($this->entries) < $this->limit) {
            $this->entries[] = $entry;
        }
    }

    /**
     * Return the number of retained entries.
     *
     * This is the number you can iterate, which is not the number of rows that
     * failed once the cap is reached — see {@see totalCount()}.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * Return the total number of failures, including entries dropped by the cap.
     *
     * @return int
     */
    public function totalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Return the number of failures dropped by the cap.
     *
     * @return int
     */
    public function droppedCount(): int
    {
        return $this->totalCount - count($this->entries);
    }

    /**
     * Whether the cap discarded any entries.
     *
     * When true, the entries here are a prefix of the failures, not all of them.
     *
     * @return bool
     */
    public function isTruncated(): bool
    {
        return $this->droppedCount() > 0;
    }

    /**
     * Return the retention limit, or null when unlimited.
     *
     * @return int|null
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }

    /**
     * Return an iterator over the retained entries.
     *
     * @return ArrayIterator<int, DeadLetterEntry>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->entries);
    }

    /**
     * Return the retained entries as an array.
     *
     * @return DeadLetterEntry[]
     */
    public function toArray(): array
    {
        return $this->entries;
    }
}
