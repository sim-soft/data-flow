<?php

namespace Simsoft\DataFlow;

/**
 * Loaders class
 */
abstract class Loader extends Processor
{
    /** @var bool Whether this loader is in dry-run mode */
    private bool $dryRun = false;

    /**
     * Set the dry-run mode for this loader.
     *
     * @param bool $dryRun Whether to enable dry-run mode
     * @return void
     */
    public function setDryRun(bool $dryRun): void
    {
        $this->dryRun = $dryRun;
    }

    /**
     * Check whether this loader is in dry-run mode.
     *
     * @return bool
     */
    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
