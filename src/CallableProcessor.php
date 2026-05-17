<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Traits\CallableDataFrame;
use Exception;
use Iterator;

/**
 * CallableProcessor class
 */
class CallableProcessor extends Processor
{
    use CallableDataFrame;

    /**
     * Constructor
     *
     * @param callable $callback
     */
    public function __construct(protected mixed $callback)
    {
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        return $this->call($dataFrame, $this->callback);
    }
}
