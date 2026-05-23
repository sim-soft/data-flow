<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Enums;

/**
 * Signal enum.
 */
enum Signal: int
{
    case Next = 1;
    case Stop = 9;
}
