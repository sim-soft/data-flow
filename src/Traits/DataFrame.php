<?php

namespace Simsoft\DataFlow\Traits;

use Iterator;

/**
 * DataFrame trait
 */
trait DataFrame
{
    /** @var Iterator|null Current data stream */
    protected ?Iterator $dataFrame = null;

    /**
     * Set data frame.
     *
     * @param Iterator|null $dataFrame
     * @return $this
     */
    public function setDataFrame(?Iterator $dataFrame): static
    {
        $this->dataFrame = $dataFrame;
        return $this;
    }

    /**
     * Get data frame.
     *
     * @return Iterator|null
     */
    public function getDataFrame(): ?Iterator
    {
        return $this->dataFrame;
    }
}
