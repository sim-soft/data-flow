<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Interfaces\Flowable;
use Simsoft\DataFlow\Traits\Macroable;

/**
 * Processor
 */
abstract class Processor implements Flowable
{
    use Macroable;

    /** @var DataFlow The current flow object. */
    private DataFlow $flow;

    /**
     * Set current flow.
     *
     * @param DataFlow $flow
     * @return $this
     */
    public function setFlow(DataFlow &$flow): static
    {
        $this->flow = $flow;
        return $this;
    }

    /**
     * Get current flow object.
     *
     * @return DataFlow
     */
    public function getFlow(): DataFlow
    {
        return $this->flow;
    }
}
