<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Metrics\CallbackMetricsExporter;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * CallbackMetricsExporterPropertyTest
 *
 * Property-based test verifying that CallbackMetricsExporter forwards exact parameters
 * to each configured closure in the correct order.
 *
 * Feature: enterprise-resilience, Property 25: CallbackMetricsExporter forwards parameters to closures
 *
 * **Validates: Requirements 11.3**
 */
#[CoversClass(CallbackMetricsExporter::class)]
class CallbackMetricsExporterPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random parameter sets for all callback methods.
     */
    public static function callbackParameterProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $stageName = 'Stage_' . bin2hex(random_bytes(random_int(2, 10)));
            $errorMessage = 'Error_' . bin2hex(random_bytes(random_int(3, 15)));
            $durationMs = random_int(1, 999999) / 100.0;
            $totalDurationMs = random_int(1, 9999999) / 100.0;
            $processedRows = random_int(0, 100000);
            $failedRows = random_int(0, $processedRows);

            yield "i={$i}" => [
                $stageName,
                $errorMessage,
                $durationMs,
                $totalDurationMs,
                $processedRows,
                $failedRows,
            ];
        }
    }

    /**
     * Property 25: CallbackMetricsExporter forwards parameters to closures
     *
     * For any event parameters passed to CallbackMetricsExporter methods,
     * the configured closure SHALL receive exactly those parameters in the correct order.
     *
     * **Validates: Requirements 11.3**
     */
    #[Test]
    #[DataProvider('callbackParameterProvider')]
    public function callbackMetricsExporterForwardsParametersToClosures(
        string $stageName,
        string $errorMessage,
        float  $durationMs,
        float  $totalDurationMs,
        int    $processedRows,
        int    $failedRows,
    ): void
    {
        // Capture parameters received by each closure
        $rowProcessedParams = null;
        $rowFailedParams = null;
        $stageDurationParams = null;
        $pipelineCompleteParams = null;

        $exporter = new CallbackMetricsExporter(
            onRowProcessed: function (string $stage) use (&$rowProcessedParams) {
                $rowProcessedParams = ['stageName' => $stage];
            },
            onRowFailed: function (string $stage, string $error) use (&$rowFailedParams) {
                $rowFailedParams = ['stageName' => $stage, 'errorMessage' => $error];
            },
            onStageDuration: function (string $stage, float $duration) use (&$stageDurationParams) {
                $stageDurationParams = ['stageName' => $stage, 'durationMs' => $duration];
            },
            onPipelineComplete: function (float $totalDuration, int $processed, int $failed) use (&$pipelineCompleteParams) {
                $pipelineCompleteParams = [
                    'totalDurationMs' => $totalDuration,
                    'processedRows' => $processed,
                    'failedRows' => $failed,
                ];
            },
        );

        // Invoke each method with the random parameters
        $exporter->recordRowProcessed($stageName);
        $exporter->recordRowFailed($stageName, $errorMessage);
        $exporter->recordStageDuration($stageName, $durationMs);
        $exporter->recordPipelineComplete($totalDurationMs, $processedRows, $failedRows);

        // Assert recordRowProcessed forwarded exact parameters
        $this->assertNotNull($rowProcessedParams, 'onRowProcessed closure must be invoked');
        $this->assertSame(
            $stageName,
            $rowProcessedParams['stageName'],
            'recordRowProcessed must forward the exact stageName to the closure',
        );

        // Assert recordRowFailed forwarded exact parameters in correct order
        $this->assertNotNull($rowFailedParams, 'onRowFailed closure must be invoked');
        $this->assertSame(
            $stageName,
            $rowFailedParams['stageName'],
            'recordRowFailed must forward the exact stageName to the closure',
        );
        $this->assertSame(
            $errorMessage,
            $rowFailedParams['errorMessage'],
            'recordRowFailed must forward the exact errorMessage to the closure',
        );

        // Assert recordStageDuration forwarded exact parameters in correct order
        $this->assertNotNull($stageDurationParams, 'onStageDuration closure must be invoked');
        $this->assertSame(
            $stageName,
            $stageDurationParams['stageName'],
            'recordStageDuration must forward the exact stageName to the closure',
        );
        $this->assertSame(
            $durationMs,
            $stageDurationParams['durationMs'],
            'recordStageDuration must forward the exact durationMs to the closure',
        );

        // Assert recordPipelineComplete forwarded exact parameters in correct order
        $this->assertNotNull($pipelineCompleteParams, 'onPipelineComplete closure must be invoked');
        $this->assertSame(
            $totalDurationMs,
            $pipelineCompleteParams['totalDurationMs'],
            'recordPipelineComplete must forward the exact totalDurationMs to the closure',
        );
        $this->assertSame(
            $processedRows,
            $pipelineCompleteParams['processedRows'],
            'recordPipelineComplete must forward the exact processedRows to the closure',
        );
        $this->assertSame(
            $failedRows,
            $pipelineCompleteParams['failedRows'],
            'recordPipelineComplete must forward the exact failedRows to the closure',
        );
    }
}
