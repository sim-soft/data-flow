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

    /**
     * Output info message.
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        print $message . PHP_EOL;
    }
}
