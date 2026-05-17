<?php

namespace Simsoft\DataFlow\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\PipelineResult;
use Simsoft\DataFlow\StageMetrics;

/**
 * PipelineResult test class.
 */
#[CoversClass(PipelineResult::class)]
class PipelineResultTest extends TestCase
{
    private PipelineResult $result;
    private DateTimeImmutable $startTime;
    private DateTimeImmutable $endTime;
    private DeadLetterCollection $deadLetters;

    /** @var StageMetrics[] */
    private array $stageMetrics;

    /** @var array<int, array{row: mixed, stageName: string, message: string, rowIndex: int}> */
    private array $failures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->startTime = new DateTimeImmutable('2024-01-01 10:00:00');
        $this->endTime = new DateTimeImmutable('2024-01-01 10:00:05');
        $this->deadLetters = new DeadLetterCollection();
        $this->stageMetrics = [
            new StageMetrics('extractor', 100, 100, 1500.0),
            new StageMetrics('transformer', 100, 95, 2000.0),
        ];
        $this->failures = [
            ['row' => ['id' => 1], 'stageName' => 'transformer', 'message' => 'Invalid data', 'rowIndex' => 3],
        ];

        $this->result = new PipelineResult(
            startTime: $this->startTime,
            endTime: $this->endTime,
            processedRows: 95,
            failedRows: 3,
            durationMs: 5000.0,
            peakMemoryBytes: 1048576,
            isDryRun: false,
            stageMetrics: $this->stageMetrics,
            deadLetters: $this->deadLetters,
            failures: $this->failures,
        );
    }

    #[Test]
    public function getStartTimeReturnsCorrectValue(): void
    {
        $this->assertSame($this->startTime, $this->result->getStartTime());
    }

    #[Test]
    public function getEndTimeReturnsCorrectValue(): void
    {
        $this->assertSame($this->endTime, $this->result->getEndTime());
    }

    #[Test]
    public function getProcessedRowsReturnsCorrectValue(): void
    {
        $this->assertSame(95, $this->result->getProcessedRows());
    }

    #[Test]
    public function getFailedRowsReturnsCorrectValue(): void
    {
        $this->assertSame(3, $this->result->getFailedRows());
    }

    #[Test]
    public function getDurationMsReturnsCorrectValue(): void
    {
        $this->assertSame(5000.0, $this->result->getDurationMs());
    }

    #[Test]
    public function getPeakMemoryBytesReturnsCorrectValue(): void
    {
        $this->assertSame(1048576, $this->result->getPeakMemoryBytes());
    }

    #[Test]
    public function isDryRunReturnsFalseWhenNotDryRun(): void
    {
        $this->assertFalse($this->result->isDryRun());
    }

    #[Test]
    public function isDryRunReturnsTrueWhenDryRun(): void
    {
        $result = new PipelineResult(
            startTime: $this->startTime,
            endTime: $this->endTime,
            processedRows: 0,
            failedRows: 0,
            durationMs: 0.0,
            peakMemoryBytes: 0,
            isDryRun: true,
            stageMetrics: [],
            deadLetters: new DeadLetterCollection(),
            failures: [],
        );

        $this->assertTrue($result->isDryRun());
    }

    #[Test]
    public function getStageMetricsReturnsCorrectArray(): void
    {
        $this->assertSame($this->stageMetrics, $this->result->getStageMetrics());
    }

    #[Test]
    public function getDeadLettersReturnsCollection(): void
    {
        $this->assertSame($this->deadLetters, $this->result->getDeadLetters());
    }

    #[Test]
    public function getFailuresReturnsCorrectArray(): void
    {
        $this->assertSame($this->failures, $this->result->getFailures());
    }

    #[Test]
    public function toArrayContainsAllExpectedKeys(): void
    {
        $array = $this->result->toArray();

        $this->assertArrayHasKey('startTime', $array);
        $this->assertArrayHasKey('endTime', $array);
        $this->assertArrayHasKey('processedRows', $array);
        $this->assertArrayHasKey('failedRows', $array);
        $this->assertArrayHasKey('durationMs', $array);
        $this->assertArrayHasKey('peakMemoryBytes', $array);
        $this->assertArrayHasKey('isDryRun', $array);
        $this->assertArrayHasKey('stageMetrics', $array);
        $this->assertArrayHasKey('deadLetters', $array);
        $this->assertArrayHasKey('failures', $array);
    }

    #[Test]
    public function toArrayValuesMatchGetters(): void
    {
        $array = $this->result->toArray();

        $this->assertSame($this->startTime->format('c'), $array['startTime']);
        $this->assertSame($this->endTime->format('c'), $array['endTime']);
        $this->assertSame(95, $array['processedRows']);
        $this->assertSame(3, $array['failedRows']);
        $this->assertSame(5000.0, $array['durationMs']);
        $this->assertSame(1048576, $array['peakMemoryBytes']);
        $this->assertFalse($array['isDryRun']);
    }

    #[Test]
    public function toArraySerializesStageMetrics(): void
    {
        $array = $this->result->toArray();

        $this->assertCount(2, $array['stageMetrics']);
        $this->assertSame('extractor', $array['stageMetrics'][0]['stageName']);
        $this->assertSame(100, $array['stageMetrics'][0]['rowsEntered']);
        $this->assertSame(100, $array['stageMetrics'][0]['rowsExited']);
        $this->assertSame(1500.0, $array['stageMetrics'][0]['durationMs']);
    }

    #[Test]
    public function toArraySerializesDeadLetters(): void
    {
        $array = $this->result->toArray();

        $this->assertArrayHasKey('count', $array['deadLetters']);
        $this->assertArrayHasKey('entries', $array['deadLetters']);
        $this->assertSame(0, $array['deadLetters']['count']);
    }
}
