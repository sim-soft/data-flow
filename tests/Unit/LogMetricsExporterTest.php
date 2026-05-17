<?php

namespace Simsoft\DataFlow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Metrics\LogMetricsExporter;

class LogMetricsExporterTest extends TestCase
{
    public function test_implements_metrics_exporter_interface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $exporter = new LogMetricsExporter($logger);

        $this->assertInstanceOf(MetricsExporter::class, $exporter);
    }

    public function test_record_row_processed_logs_info_with_stage_name(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['stage'] === 'extract'
                        && isset($context['event']);
                }),
            );

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowProcessed('extract');
    }

    public function test_record_row_failed_logs_warning_with_stage_and_error(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['stage'] === 'loader'
                        && $context['error'] === 'Connection timeout';
                }),
            );

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowFailed('loader', 'Connection timeout');
    }

    public function test_record_stage_duration_logs_info_with_stage_and_duration(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['stage'] === 'transform'
                        && $context['duration_ms'] === 456.78;
                }),
            );

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordStageDuration('transform', 456.78);
    }

    public function test_record_pipeline_complete_logs_info_with_all_metrics(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                $this->anything(),
                $this->callback(function (array $context) {
                    return $context['total_duration_ms'] === 5000.0
                        && $context['processed_rows'] === 100
                        && $context['failed_rows'] === 3;
                }),
            );

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordPipelineComplete(5000.0, 100, 3);
    }

    public function test_record_row_processed_uses_info_level(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');
        $logger->expects($this->never())->method('warning');

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowProcessed('stage1');
    }

    public function test_record_row_failed_uses_warning_level(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger->expects($this->once())->method('warning');

        $exporter = new LogMetricsExporter($logger);
        $exporter->recordRowFailed('stage1', 'some error');
    }
}
