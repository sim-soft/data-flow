<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Interfaces\Flowable;

/**
 * Processor
 */
abstract class Processor implements Flowable
{
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
