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
}
