<?php

namespace Simsoft\DataFlow\Transformers;

use Simsoft\DataFlow\Transformer;
use Closure;
use Iterator;

/**
 * Filter class
 */
class Filter extends Transformer
{
    /**
     * Constructor.
     *
     * @param Closure $callback
     */
    public function __construct(protected Closure $callback)
    {

    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        if ($dataFrame) {
            foreach ($dataFrame as $index => $value) {
                if ((bool)($this->callback)($value, $index)) {
                    yield $index => $value;
                }
            }
        }
    }
}
