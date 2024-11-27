<?php

namespace Simsoft\DataFlow\Extractors;

use Simsoft\DataFlow\Extractor;
use ArrayIterator;
use Exception;
use Iterator;
use Traversable;

/**
 * IterableExtractor class
 *
 * Extract data from iterable object.
 */
class IterableExtractor extends Extractor
{
    /**
     * Constructor
     *
     * @param mixed $iterable
     * @throws Exception
     */
    final public function __construct(protected mixed $iterable)
    {
        if (is_array($this->iterable)) {
            $this->iterable = new ArrayIterator($this->iterable);
        }

        if (!$this->iterable instanceof Traversable) {
            throw new Exception("Invalid iterable provided");
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        return $this->iterable;
    }
}
