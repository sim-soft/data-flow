<?php

namespace Simsoft\DataFlow\Tests\Unit;

use Closure;
use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Metrics\CallbackMetricsExporter;

class CallbackMetricsExporterTest extends TestCase
{
    public function test_implements_metrics_exporter_interface(): void
    {
        $exporter = new CallbackMetricsExporter();
        $this->assertInstanceOf(MetricsExporter::class, $exporter);
    }

    public function test_no_op_when_all_closures_are_null(): void
    {
        $exporter = new CallbackMetricsExporter();

        // Should not throw any exceptions
        $exporter->recordRowProcessed('stage1');
        $exporter->recordRowFailed('stage1', new \RuntimeException('error'));
        $exporter->recordStageDuration('stage1', 123.45);
        $exporter->recordPipelineComplete(1000.0, 50, 2);

        $this->assertTrue(true); // No exception means success
    }

    public function test_invokes_on_row_processed_closure_with_stage_name(): void
    {
        $captured = [];
        $exporter = new CallbackMetricsExporter(
            onRowProcessed: function (string $stageName) use (&$captured) {
                $captured[] = $stageName;
            },
        );

        $exporter->recordRowProcessed('extract');
        $exporter->recordRowProcessed('transform');

        $this->assertSame(['extract', 'transform'], $captured);
    }

    public function test_invokes_on_row_failed_closure_with_parameters(): void
    {
        $captured = [];
        $exporter = new CallbackMetricsExporter(
            onRowFailed: function (string $stageName, \Throwable $error) use (&$captured) {
                $captured[] = [$stageName, $error->getMessage(), get_class($error)];
            },
        );

        $error = new \RuntimeException('Connection timeout');
        $exporter->recordRowFailed('loader', $error);

        $this->assertSame([['loader', 'Connection timeout', \RuntimeException::class]], $captured);
    }

    public function test_invokes_on_stage_duration_closure_with_parameters(): void
    {
        $captured = [];
        $exporter = new CallbackMetricsExporter(
            onStageDuration: function (string $stageName, float $durationMs) use (&$captured) {
                $captured[] = [$stageName, $durationMs];
            },
        );

        $exporter->recordStageDuration('transform', 456.78);

        $this->assertSame([['transform', 456.78]], $captured);
    }

    public function test_invokes_on_pipeline_complete_closure_with_parameters(): void
    {
        $captured = [];
        $exporter = new CallbackMetricsExporter(
            onPipelineComplete: function (float $totalDurationMs, int $processedRows, int $failedRows) use (&$captured) {
                $captured[] = [$totalDurationMs, $processedRows, $failedRows];
            },
        );

        $exporter->recordPipelineComplete(5000.0, 100, 3);

        $this->assertSame([[5000.0, 100, 3]], $captured);
    }

    public function test_only_configured_closures_are_invoked(): void
    {
        $rowProcessedCalled = false;
        $exporter = new CallbackMetricsExporter(
            onRowProcessed: function () use (&$rowProcessedCalled) {
                $rowProcessedCalled = true;
            },
        );

        // These should be no-ops since their closures are null
        $exporter->recordRowFailed('stage', new \RuntimeException('error'));
        $exporter->recordStageDuration('stage', 100.0);
        $exporter->recordPipelineComplete(1000.0, 10, 1);

        $this->assertFalse($rowProcessedCalled);

        // This should invoke the closure
        $exporter->recordRowProcessed('stage');
        $this->assertTrue($rowProcessedCalled);
    }
}
