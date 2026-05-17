<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Metrics\LogMetricsExporter;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * LogMetricsExporterPropertyTest
 *
 * Property 24: LogMetricsExporter log messages contain event parameters
 *
 * For any stage name, error message, duration, and row counts passed to
 * LogMetricsExporter methods, the resulting log context SHALL contain all
 * provided parameter values.
 *
 * **Validates: Requirements 10.3, 10.4, 10.5, 10.6**
 */
#[CoversClass(LogMetricsExporter::class)]
class LogMetricsExporterPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random inputs for recordRowProcessed.
     */
    public static function recordRowProcessedProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $stageName = self::randomStageName();
            yield "i={$i},stage={$stageName}" => [$stageName];
        }
    }

    /**
     * Data provider generating 100 random inputs for recordRowFailed.
     */
    public static function recordRowFailedProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $stageName = self::randomStageName();
            $errorMessage = self::randomErrorMessage();
            yield "i={$i},stage={$stageName}" => [$stageName, $errorMessage];
        }
    }

    /**
     * Data provider generating 100 random inputs for recordStageDuration.
     */
    public static function recordStageDurationProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $stageName = self::randomStageName();
            $durationMs = round(mt_rand(0, 999999) / 100.0, 2);
            yield "i={$i},stage={$stageName},dur={$durationMs}" => [$stageName, $durationMs];
        }
    }

    /**
     * Data provider generating 100 random inputs for recordPipelineComplete.
     */
    public static function recordPipelineCompleteProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $totalDurationMs = round(mt_rand(0, 9999999) / 100.0, 2);
            $processedRows = random_int(0, 100000);
            $failedRows = random_int(0, min($processedRows, 10000));
            yield "i={$i},dur={$totalDurationMs},proc={$processedRows},fail={$failedRows}" => [
                $totalDurationMs,
                $processedRows,
                $failedRows,
            ];
        }
    }

    /**
     * Property 24a: recordRowProcessed context contains 'stage' key with the stage name.
     *
     * **Validates: Requirements 10.3**
     */
    #[Test]
    #[DataProvider('recordRowProcessedProvider')]
    public function recordRowProcessedContextContainsStage(string $stageName): void
    {
        $capturedContext = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowProcessed($stageName);

        $this->assertNotNull($capturedContext, 'Logger info() must be called');
        $this->assertArrayHasKey('stage', $capturedContext, 'Context must contain "stage" key');
        $this->assertSame(
            $stageName,
            $capturedContext['stage'],
            "Context 'stage' must equal the provided stage name '{$stageName}'",
        );
    }

    /**
     * Property 24b: recordRowFailed context contains 'stage' and 'error' keys.
     *
     * **Validates: Requirements 10.4**
     */
    #[Test]
    #[DataProvider('recordRowFailedProvider')]
    public function recordRowFailedContextContainsStageAndError(string $stageName, string $errorMessage): void
    {
        $capturedContext = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowFailed($stageName, $errorMessage);

        $this->assertNotNull($capturedContext, 'Logger warning() must be called');
        $this->assertArrayHasKey('stage', $capturedContext, 'Context must contain "stage" key');
        $this->assertArrayHasKey('error', $capturedContext, 'Context must contain "error" key');
        $this->assertSame(
            $stageName,
            $capturedContext['stage'],
            "Context 'stage' must equal the provided stage name '{$stageName}'",
        );
        $this->assertSame(
            $errorMessage,
            $capturedContext['error'],
            "Context 'error' must equal the provided error message",
        );
    }

    /**
     * Property 24c: recordStageDuration context contains 'stage' and 'duration_ms' keys.
     *
     * **Validates: Requirements 10.5**
     */
    #[Test]
    #[DataProvider('recordStageDurationProvider')]
    public function recordStageDurationContextContainsStageAndDuration(string $stageName, float $durationMs): void
    {
        $capturedContext = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordStageDuration($stageName, $durationMs);

        $this->assertNotNull($capturedContext, 'Logger info() must be called');
        $this->assertArrayHasKey('stage', $capturedContext, 'Context must contain "stage" key');
        $this->assertArrayHasKey('duration_ms', $capturedContext, 'Context must contain "duration_ms" key');
        $this->assertSame(
            $stageName,
            $capturedContext['stage'],
            "Context 'stage' must equal the provided stage name '{$stageName}'",
        );
        $this->assertSame(
            $durationMs,
            $capturedContext['duration_ms'],
            "Context 'duration_ms' must equal the provided duration {$durationMs}",
        );
    }

    /**
     * Property 24d: recordPipelineComplete context contains 'total_duration_ms', 'processed_rows', 'failed_rows'.
     *
     * **Validates: Requirements 10.6**
     */
    #[Test]
    #[DataProvider('recordPipelineCompleteProvider')]
    public function recordPipelineCompleteContextContainsAllMetrics(
        float $totalDurationMs,
        int   $processedRows,
        int   $failedRows,
    ): void
    {
        $capturedContext = null;

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordPipelineComplete($totalDurationMs, $processedRows, $failedRows);

        $this->assertNotNull($capturedContext, 'Logger info() must be called');
        $this->assertArrayHasKey('total_duration_ms', $capturedContext, 'Context must contain "total_duration_ms" key');
        $this->assertArrayHasKey('processed_rows', $capturedContext, 'Context must contain "processed_rows" key');
        $this->assertArrayHasKey('failed_rows', $capturedContext, 'Context must contain "failed_rows" key');
        $this->assertSame(
            $totalDurationMs,
            $capturedContext['total_duration_ms'],
            "Context 'total_duration_ms' must equal the provided value {$totalDurationMs}",
        );
        $this->assertSame(
            $processedRows,
            $capturedContext['processed_rows'],
            "Context 'processed_rows' must equal the provided value {$processedRows}",
        );
        $this->assertSame(
            $failedRows,
            $capturedContext['failed_rows'],
            "Context 'failed_rows' must equal the provided value {$failedRows}",
        );
    }

    /**
     * Generate a random stage name.
     */
    private static function randomStageName(): string
    {
        $prefixes = ['extract', 'transform', 'load', 'validate', 'filter', 'map', 'chunk', 'stage'];
        $suffixes = ['_csv', '_db', '_api', '_file', '_queue', '_cache', '_stream', ''];
        $prefix = $prefixes[array_rand($prefixes)];
        $suffix = $suffixes[array_rand($suffixes)];

        return $prefix . $suffix . '_' . random_int(1, 9999);
    }

    /**
     * Generate a random error message.
     */
    private static function randomErrorMessage(): string
    {
        $messages = [
            'Connection timeout after %dms',
            'Invalid response code: %d',
            'Row validation failed at index %d',
            'Database constraint violation #%d',
            'File not found: /tmp/data_%d.csv',
            'Memory limit exceeded at row %d',
            'Rate limit hit, retry after %ds',
            'Unexpected null value in column_%d',
        ];

        return sprintf($messages[array_rand($messages)], random_int(1, 99999));
    }
}
