<?php

namespace Simsoft\DataFlow\Interfaces;

use Iterator;

/**
 * Flowable Interface
 */
interface Flowable
{
    /**
     * Invoke method.
     *
     * @param Iterator|null $dataFrame
     * @return Iterator
     */
    public function __invoke(?Iterator $dataFrame): Iterator;
}
