<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Metrics\CallbackMetricsExporter;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * MetricsExporterEventCountsPropertyTest class
 *
 * Feature: enterprise-resilience, Property 23: Metrics exporter receives correct event counts
 *
 * Property-based test verifying that for any random number of calls to
 * recordRowProcessed, recordRowFailed, and recordStageDuration, the
 * CallbackMetricsExporter receives exactly that many invocations for each event type.
 *
 * **Validates: Requirements 9.3, 9.4, 9.5**
 */
#[CoversClass(CallbackMetricsExporter::class)]
class MetricsExporterEventCountsPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random event count combinations.
     *
     * Each case provides:
     * - processedCount: random int in [0, 200]
     * - failedCount: random int in [0, 50]
     * - durationCount: random int in [0, 20]
     *
     * @return Generator
     */
    public static function eventCountProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $processedCount = random_int(0, 200);
            $failedCount = random_int(0, 50);
            $durationCount = random_int(0, 20);

            yield "processed={$processedCount},failed={$failedCount},duration={$durationCount},i={$i}" => [
                $processedCount,
                $failedCount,
                $durationCount,
            ];
        }
    }

    /**
     * Property 23: Metrics exporter receives correct event counts
     *
     * For any random number of calls to recordRowProcessed, recordRowFailed,
     * and recordStageDuration, the CallbackMetricsExporter receives exactly
     * that many invocations for each event type.
     *
     * **Validates: Requirements 9.3, 9.4, 9.5**
     */
    #[Test]
    #[DataProvider('eventCountProvider')]
    public function metricsExporterReceivesCorrectEventCounts(
        int $processedCount,
        int $failedCount,
        int $durationCount,
    ): void
    {
        $actualProcessed = 0;
        $actualFailed = 0;
        $actualDuration = 0;

        $exporter = new CallbackMetricsExporter(
            onRowProcessed: function (string $stageName) use (&$actualProcessed): void {
                $actualProcessed++;
            },
            onRowFailed: function (string $stageName, string $errorMessage) use (&$actualFailed): void {
                $actualFailed++;
            },
            onStageDuration: function (string $stageName, float $durationMs) use (&$actualDuration): void {
                $actualDuration++;
            },
        );

        // Invoke recordRowProcessed the expected number of times
        for ($j = 0; $j < $processedCount; $j++) {
            $exporter->recordRowProcessed('stage_' . ($j % 5));
        }

        // Invoke recordRowFailed the expected number of times
        for ($j = 0; $j < $failedCount; $j++) {
            $exporter->recordRowFailed('stage_' . ($j % 3), 'error_' . $j);
        }

        // Invoke recordStageDuration the expected number of times
        for ($j = 0; $j < $durationCount; $j++) {
            $exporter->recordStageDuration('stage_' . $j, (float)random_int(1, 5000));
        }

        $this->assertSame(
            $processedCount,
            $actualProcessed,
            "recordRowProcessed() should have been called exactly {$processedCount} times, got {$actualProcessed}"
        );

        $this->assertSame(
            $failedCount,
            $actualFailed,
            "recordRowFailed() should have been called exactly {$failedCount} times, got {$actualFailed}"
        );

        $this->assertSame(
            $durationCount,
            $actualDuration,
            "recordStageDuration() should have been called exactly {$durationCount} times, got {$actualDuration}"
        );
    }
}
