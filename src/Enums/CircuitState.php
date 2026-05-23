<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Enums;

/**
 * CircuitState enum.
 */
enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
