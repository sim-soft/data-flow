<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Interfaces\Flowable;
use Simsoft\DataFlow\Traits\PayloadHandling;

/**
 * Processor
 */
abstract class Processor implements Flowable
{
    use PayloadHandling;
}
