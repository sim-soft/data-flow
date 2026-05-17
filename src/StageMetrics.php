<?php

namespace Simsoft\DataFlow;

/**
 * StageMetrics value object.
 *
 * Holds per-stage execution metrics including row counts and timing.
 */
final readonly class StageMetrics
{
    /**
     * Constructor.
     *
     * @param string $stageName The stage identifier.
     * @param int $rowsEntered Number of rows received by the stage.
     * @param int $rowsExited Number of rows emitted by the stage.
     * @param float $durationMs Time spent in the stage in milliseconds.
     */
    public function __construct(
        public string $stageName,
        public int    $rowsEntered,
        public int    $rowsExited,
        public float  $durationMs,
    )
    {
    }
}
