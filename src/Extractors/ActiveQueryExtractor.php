<?php

namespace Simsoft\DataFlow\Extractors;

use Simsoft\DataFlow\Extractor;
use Iterator;
use Simsoft\DB\MySQL\Builder\ActiveQuery;

/**
 * ActiveQueryExtractor class
 */
class ActiveQueryExtractor extends Extractor
{
    /** @var bool Output result as array. Default: false */
    protected bool $toArray = false;

    /** @var int Maximum size of data per loop. */
    protected int $size = 100;

    protected bool $debugMode = false;

    /**
     * Constructor.
     *
     * @param ActiveQuery $query The Active Query instance.
     */
    final public function __construct(protected ActiveQuery $query)
    {

    }

    /**
     * Factory method.
     *
     * @param ActiveQuery $query The Active Query instance.
     * @param int $size Maximum size of data per loop.
     * @return static
     */
    public static function make(ActiveQuery $query, int $size = 100): static
    {
        return (new static($query))->size($size);
    }

    /**
     * Debug by showing full SQL query.
     *
     * @return $this
     */
    public function debug(): static
    {
        //print 'Query SQL: ' . (clone $this->query)->getFullSQL() . "\n"  ;
        $this->debugMode = true;
        return $this;
    }

    /**
     * Set maximum size data to be process per loop.
     *
     * @param int $size
     * @return $this
     */
    public function size(int $size): static
    {
        $this->size = $size;
        return $this;
    }

    /**
     * Enable output as array.
     *
     * @return $this
     */
    public function toArray(): static
    {
        $this->toArray = true;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        $collection = $this->query->each($this->size);
        if ($this->debugMode) {
            $collection->debug();
        }

        if ($this->toArray) {
            $collection->toArray();
        }

        yield from $collection;
    }
}
