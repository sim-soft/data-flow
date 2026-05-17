<?php

namespace Simsoft\DataFlow\Enums;

/**
 * ErrorStrategy enum.
 *
 * Defines how a pipeline stage handles row-level exceptions.
 */
enum ErrorStrategy: string
{
    case Throw = 'throw';
    case Skip = 'skip';
    case Retry = 'retry';
    case LogAndContinue = 'log-and-continue';
}
