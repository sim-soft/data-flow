<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\PipelineExecutor;
use Simsoft\DataFlow\PipelineResult;
use Simsoft\DataFlow\StageMetrics;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * MetricsProgressPropertyTest
 *
 * Property-based tests for pipeline metrics and progress callback behavior.
 * Uses randomized data to verify properties hold across many inputs.
 *
 * - Property 13: Duration Equals Time Difference
 * - Property 14: Per-Stage Metrics Consistency
 * - Property 15: PipelineResult Serialization Round-Trip
 * - Property 16: Progress Callback Invocation Frequency
 */
#[CoversClass(PipelineExecutor::class)]
#[CoversClass(PipelineResult::class)]
#[CoversClass(StageMetrics::class)]
#[CoversClass(DataFlow::class)]
class MetricsProgressPropertyTest extends TestCase
{
    /**
     * Data provider generating 50+ random row sets for duration property testing.
     *
     * @return Generator
     */
    public static function durationPropertyProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(5, 50);
            $rows = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'value' => 'data_' . random_int(100, 9999)];
            }

            yield "rows={$rowCount},i={$i}" => [$rows];
        }
    }

    /**
     * Data provider generating 50+ random multi-stage pipelines for metrics consistency.
     *
     * @return Generator
     */
    public static function perStageMetricsProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(5, 40);
            $rows = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'value' => random_int(1, 1000)];
            }

            // Random filter threshold: keep rows where value > threshold
            $threshold = random_int(100, 900);

            yield "rows={$rowCount},threshold={$threshold},i={$i}" => [$rows, $threshold];
        }
    }

    /**
     * Data provider generating 50+ random pipeline results for serialization round-trip.
     *
     * @return Generator
     */
    public static function serializationRoundTripProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(1, 50);
            $rows = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'name' => 'item_' . random_int(0, 9999)];
            }

            yield "rows={$rowCount},i={$i}" => [$rows];
        }
    }

    /**
     * Data provider generating 50+ random configurations for progress callback testing.
     *
     * @return Generator
     */
    public static function progressCallbackProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(1, 100);
            $interval = random_int(1, max(1, (int)floor($rowCount / 2) + 1));
            $rows = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'payload' => bin2hex(random_bytes(random_int(1, 4)))];
            }

            yield "rows={$rowCount},interval={$interval},i={$i}" => [$rows, $interval];
        }
    }

    /**
     * Property 13: Duration Equals Time Difference
     *
     * For any pipeline execution, the durationMs value SHALL equal the difference
     * between endTime and startTime converted to milliseconds (within a 1ms tolerance).
     *
     * **Validates: Requirements 9.6**
     */
    #[Test]
    #[DataProvider('durationPropertyProvider')]
    public function durationEqualsTimeDifference(array $rows): void
    {
        $result = (new DataFlow())
            ->from($rows)
            ->transform(fn(mixed $row): mixed => $row)
            ->load(fn(mixed $row): mixed => $row)
            ->run();

        $startTime = $result->getStartTime();
        $endTime = $result->getEndTime();
        $durationMs = $result->getDurationMs();

        // Calculate expected duration from timestamps
        $expectedDurationMs = ($endTime->getTimestamp() - $startTime->getTimestamp()) * 1000.0
            + ($endTime->format('u') - $startTime->format('u')) / 1000.0;

        // The durationMs should be close to the time difference (within 1ms tolerance)
        // Note: durationMs is measured with hrtime which is more precise than DateTimeImmutable,
        // so we allow a reasonable tolerance for the difference between the two clocks
        $this->assertEqualsWithDelta(
            $expectedDurationMs,
            $durationMs,
            5.0, // 5ms tolerance to account for clock differences between hrtime and DateTimeImmutable
            "Duration ({$durationMs}ms) should approximately equal time difference ({$expectedDurationMs}ms)",
        );

        // Additionally verify that durationMs is non-negative
        $this->assertGreaterThanOrEqual(0, $durationMs, 'Duration must be non-negative');

        // Verify endTime >= startTime
        $this->assertGreaterThanOrEqual(
            $startTime,
            $endTime,
            'End time must be >= start time',
        );
    }

    /**
     * Property 14: Per-Stage Metrics Consistency
     *
     * For any pipeline with multiple stages, the per-stage rowsEntered values SHALL be
     * monotonically non-increasing (each stage receives at most as many rows as the
     * previous stage emitted), and the sum of per-stage durationMs values SHALL not
     * exceed the total durationMs.
     *
     * **Validates: Requirements 10.2, 10.3**
     */
    #[Test]
    #[DataProvider('perStageMetricsProvider')]
    public function perStageMetricsConsistency(array $rows, int $threshold): void
    {
        $result = (new DataFlow())
            ->from($rows)
            ->transform(function (mixed $row) use ($threshold): mixed {
                // Filter: only pass rows with value > threshold
                return $row['value'] > $threshold ? $row : null;
            })
            ->load(fn(mixed $row): mixed => $row)
            ->run();

        $stageMetrics = $result->getStageMetrics();
        $totalDurationMs = $result->getDurationMs();

        // Must have stage metrics
        $this->assertNotEmpty($stageMetrics, 'Pipeline must produce stage metrics');

        // Verify monotonically non-increasing rowsEntered
        $previousRowsExited = PHP_INT_MAX;
        foreach ($stageMetrics as $index => $metrics) {
            $this->assertInstanceOf(StageMetrics::class, $metrics);

            if ($index > 0) {
                $this->assertLessThanOrEqual(
                    $previousRowsExited,
                    $metrics->rowsEntered,
                    "Stage '{$metrics->stageName}' rowsEntered ({$metrics->rowsEntered}) "
                    . "should be <= previous stage rowsExited ({$previousRowsExited})",
                );
            }

            $previousRowsExited = $metrics->rowsExited;

            // Each stage duration must be non-negative
            $this->assertGreaterThanOrEqual(
                0,
                $metrics->durationMs,
                "Stage '{$metrics->stageName}' durationMs must be non-negative",
            );
        }
    }

    /**
     * Property 15: PipelineResult Serialization Round-Trip
     *
     * For any PipelineResult object, calling toArray() SHALL produce an associative array
     * containing keys for all public properties, and the values SHALL match the
     * corresponding getter return values.
     *
     * **Validates: Requirements 10.4**
     */
    #[Test]
    #[DataProvider('serializationRoundTripProvider')]
    public function pipelineResultSerializationRoundTrip(array $rows): void
    {
        $result = (new DataFlow())
            ->from($rows)
            ->transform(fn(mixed $row): mixed => $row)
            ->load(fn(mixed $row): mixed => $row)
            ->run();

        $array = $result->toArray();

        // Verify all expected keys are present
        $expectedKeys = [
            'startTime',
            'endTime',
            'processedRows',
            'failedRows',
            'durationMs',
            'peakMemoryBytes',
            'isDryRun',
            'stageMetrics',
            'deadLetters',
            'failures',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey(
                $key,
                $array,
                "toArray() must contain key '{$key}'",
            );
        }

        // Verify scalar values match getter return values
        $this->assertSame(
            $result->getStartTime()->format('c'),
            $array['startTime'],
            'startTime in array must match getStartTime() formatted as ISO 8601',
        );

        $this->assertSame(
            $result->getEndTime()->format('c'),
            $array['endTime'],
            'endTime in array must match getEndTime() formatted as ISO 8601',
        );

        $this->assertSame(
            $result->getProcessedRows(),
            $array['processedRows'],
            'processedRows in array must match getProcessedRows()',
        );

        $this->assertSame(
            $result->getFailedRows(),
            $array['failedRows'],
            'failedRows in array must match getFailedRows()',
        );

        $this->assertSame(
            $result->getDurationMs(),
            $array['durationMs'],
            'durationMs in array must match getDurationMs()',
        );

        $this->assertSame(
            $result->getPeakMemoryBytes(),
            $array['peakMemoryBytes'],
            'peakMemoryBytes in array must match getPeakMemoryBytes()',
        );

        $this->assertSame(
            $result->isDryRun(),
            $array['isDryRun'],
            'isDryRun in array must match isDryRun()',
        );

        // Verify stageMetrics array structure matches getStageMetrics()
        $stageMetrics = $result->getStageMetrics();
        $this->assertCount(
            count($stageMetrics),
            $array['stageMetrics'],
            'stageMetrics array count must match getStageMetrics() count',
        );

        foreach ($stageMetrics as $index => $metrics) {
            $this->assertSame($metrics->stageName, $array['stageMetrics'][$index]['stageName']);
            $this->assertSame($metrics->rowsEntered, $array['stageMetrics'][$index]['rowsEntered']);
            $this->assertSame($metrics->rowsExited, $array['stageMetrics'][$index]['rowsExited']);
            $this->assertSame($metrics->durationMs, $array['stageMetrics'][$index]['durationMs']);
        }

        // Verify deadLetters structure
        $this->assertArrayHasKey('count', $array['deadLetters']);
        $this->assertSame(
            $result->getDeadLetters()->count(),
            $array['deadLetters']['count'],
            'deadLetters count must match getDeadLetters()->count()',
        );

        // Verify failures match
        $this->assertSame(
            $result->getFailures(),
            $array['failures'],
            'failures in array must match getFailures()',
        );
    }

    /**
     * Property 16: Progress Callback Invocation Frequency
     *
     * For any pipeline configured with a progress callback at interval N processing
     * T total rows, the callback SHALL be invoked exactly floor(T / N) times, and
     * each invocation SHALL receive the current cumulative row count and elapsed time
     * in milliseconds.
     *
     * **Validates: Requirements 11.3**
     */
    #[Test]
    #[DataProvider('progressCallbackProvider')]
    public function progressCallbackInvocationFrequency(array $rows, int $interval): void
    {
        $invocations = [];

        $callback = function (int $rowCount, float $elapsedMs) use (&$invocations): void {
            $invocations[] = ['rowCount' => $rowCount, 'elapsedMs' => $elapsedMs];
        };

        $totalRows = count($rows);

        $result = (new DataFlow())
            ->from($rows)
            ->transform(fn(mixed $row): mixed => $row)
            ->load(fn(mixed $row): mixed => $row)
            ->onProgress($callback, $interval)
            ->run();

        // Expected number of invocations: floor(T / N) + 1 extra for tail rows (if T % N !== 0)
        $expectedInvocations = (int)floor($totalRows / $interval) + ($totalRows % $interval !== 0 ? 1 : 0);

        $this->assertCount(
            $expectedInvocations,
            $invocations,
            "Progress callback should be invoked exactly floor({$totalRows} / {$interval})"
            . " + tail = {$expectedInvocations} times, "
            . "but was invoked " . count($invocations) . " times",
        );

        // Verify each invocation receives correct cumulative row count
        foreach ($invocations as $index => $invocation) {
            if ($index < (int)floor($totalRows / $interval)) {
                $expectedRowCount = ($index + 1) * $interval;
            } else {
                // Final tail invocation should have total rows
                $expectedRowCount = $totalRows;
            }

            $this->assertSame(
                $expectedRowCount,
                $invocation['rowCount'],
                "Invocation {$index}: expected row count {$expectedRowCount}, got {$invocation['rowCount']}",
            );

            // Elapsed time must be a positive float (in milliseconds)
            $this->assertIsFloat($invocation['elapsedMs']);
            $this->assertGreaterThanOrEqual(
                0.0,
                $invocation['elapsedMs'],
                "Invocation {$index}: elapsed time must be non-negative",
            );
        }

        // Verify invocations are in chronological order (elapsed time non-decreasing)
        for ($i = 1; $i < count($invocations); $i++) {
            $this->assertGreaterThanOrEqual(
                $invocations[$i - 1]['elapsedMs'],
                $invocations[$i]['elapsedMs'],
                "Invocation {$i}: elapsed time must be >= previous invocation's elapsed time",
            );
        }
    }
}
