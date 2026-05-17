<?php

namespace Simsoft\DataFlow\Tests\Logging;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * NullLogger test class.
 */
#[CoversClass(NullLogger::class)]
class NullLoggerTest extends TestCase
{
    #[Test]
    public function implementsLoggerInterface(): void
    {
        $logger = new NullLogger();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[Test]
    public function logDoesNotThrow(): void
    {
        $logger = new NullLogger();

        $logger->emergency('test');
        $logger->alert('test');
        $logger->critical('test');
        $logger->error('test');
        $logger->warning('test');
        $logger->notice('test');
        $logger->info('test');
        $logger->debug('test');
        $logger->log('custom', 'test', ['key' => 'value']);

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }
}
