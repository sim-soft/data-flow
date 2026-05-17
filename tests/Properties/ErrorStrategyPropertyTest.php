<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * ErrorStrategyPropertyTest
 *
 * Property-based tests for error strategy behavior in the StageRunner.
 * Uses randomized data across 50+ iterations to verify universal properties.
 *
 * **Validates: Requirements 1.3, 1.4, 1.5, 1.6**
 */
#[CoversClass(StageRunner::class)]
class ErrorStrategyPropertyTest extends TestCase
{
    /**
     * Create a configurable test processor that throws on specified row indices.
     *
     * @param array<int> $failingIndices Zero-based indices of rows that should throw.
     * @param \ArrayObject<int, int> $invocationTracker Shared tracker for invocation counts per row index.
     * @return Processor
     */
    private function createConfigurableProcessor(array $failingIndices, \ArrayObject $invocationTracker): Processor
    {
        return new class ($failingIndices, $invocationTracker) extends Processor {
            /** @var array<int> */
            private array $failingIndices;

            /** @var \ArrayObject<int, int> */
            private \ArrayObject $invocationTracker;

            /**
             * @param array<int> $failingIndices
             * @param \ArrayObject<int, int> $invocationTracker
             */
            public function __construct(array $failingIndices, \ArrayObject $invocationTracker)
            {
                $this->failingIndices = $failingIndices;
                $this->invocationTracker = $invocationTracker;
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                if ($dataFrame === null) {
                    return new ArrayIterator([]);
                }

                $results = [];
                foreach ($dataFrame as $row) {
                    $index = $row['__index'] ?? -1;

                    if (!isset($this->invocationTracker[$index])) {
                        $this->invocationTracker[$index] = 0;
                    }
                    $this->invocationTracker[$index]++;

                    if (in_array($index, $this->failingIndices, true)) {
                        throw new RuntimeException("Row {$index} failed processing");
                    }

                    $results[] = $row;
                }

                return new ArrayIterator($results);
            }
        };
    }

    /**
     * Generate random row data with an index marker.
     *
     * @param int $index The row index.
     * @return array<string, mixed>
     */
    private function generateRandomRow(int $index): array
    {
        return [
            '__index' => $index,
            'id' => random_int(1, 99999),
            'name' => 'item_' . random_int(1000, 9999),
            'value' => random_int(-500, 500) / 10.0,
            'active' => (bool)random_int(0, 1),
        ];
    }

    /**
     * Data provider for Property 1: Throw Strategy Propagates Exceptions.
     *
     * Generates 50+ random row sets where at least one row will fail.
     *
     * @return Generator
     */
    public static function throwStrategyProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(2, 20);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    '__index' => $j,
                    'id' => random_int(1, 99999),
                    'name' => 'item_' . random_int(1000, 9999),
                    'value' => random_int(-500, 500) / 10.0,
                    'active' => (bool)random_int(0, 1),
                ];
            }
            // Pick a random row to fail (not the last one, so we can verify no subsequent rows processed)
            $failIndex = random_int(0, $rowCount - 2);

            yield "rows={$rowCount},failAt={$failIndex},i={$i}" => [$rows, $failIndex];
        }
    }

    /**
     * Property 1: Throw Strategy Propagates Exceptions
     *
     * For any row that causes an exception in a stage configured with the throw
     * error strategy, the pipeline SHALL propagate that exception immediately
     * without catching it, and no subsequent rows SHALL be processed.
     *
     * **Validates: Requirements 1.3**
     */
    #[Test]
    #[DataProvider('throwStrategyProvider')]
    public function throwStrategyPropagatesExceptions(array $rows, int $failIndex): void
    {
        $invocationTracker = new \ArrayObject();
        $processor = $this->createConfigurableProcessor([$failIndex], $invocationTracker);
        $processor->withName('test-throw-stage');

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $exceptionCaught = false;
        $processedRows = [];

        try {
            $generator = $runner->run(
                $processor,
                $input,
                ErrorStrategy::Throw,
                null,
                $logger,
                $deadLetters,
                null,
            );

            foreach ($generator as $row) {
                $processedRows[] = $row;
            }
        } catch (RuntimeException $e) {
            $exceptionCaught = true;
        }

        // The exception MUST be propagated
        $this->assertTrue(
            $exceptionCaught,
            "Throw strategy must propagate the exception for failing row at index {$failIndex}"
        );

        // No rows after the failing index should have been processed
        foreach ($processedRows as $row) {
            $this->assertLessThan(
                $failIndex,
                $row['__index'],
                "No rows at or after the failing index ({$failIndex}) should be in the output"
            );
        }

        // Dead-letter collection should be empty (throw strategy doesn't collect)
        $this->assertCount(0, $deadLetters);
    }

    /**
     * Data provider for Property 2: Skip Strategy Excludes Failing Rows.
     *
     * Generates 50+ random row sets with random failing indices.
     *
     * @return Generator
     */
    public static function skipStrategyProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(3, 25);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    '__index' => $j,
                    'id' => random_int(1, 99999),
                    'name' => 'item_' . random_int(1000, 9999),
                    'value' => random_int(-500, 500) / 10.0,
                    'active' => (bool)random_int(0, 1),
                ];
            }
            // Pick random subset of rows to fail
            $failCount = random_int(1, max(1, (int)($rowCount / 3)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failingIndices = array_slice($allIndices, 0, $failCount);

            yield "rows={$rowCount},fails={$failCount},i={$i}" => [$rows, $failingIndices];
        }
    }

    /**
     * Property 2: Skip Strategy Excludes Failing Rows
     *
     * For any sequence of rows processed by a stage configured with the skip
     * error strategy, the output iterator SHALL contain exactly those rows that
     * did not cause an exception, in their original order.
     *
     * **Validates: Requirements 1.4**
     */
    #[Test]
    #[DataProvider('skipStrategyProvider')]
    public function skipStrategyExcludesFailingRows(array $rows, array $failingIndices): void
    {
        $invocationTracker = new \ArrayObject();
        $processor = $this->createConfigurableProcessor($failingIndices, $invocationTracker);
        $processor->withName('test-skip-stage');

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $generator = $runner->run(
            $processor,
            $input,
            ErrorStrategy::Skip,
            null,
            $logger,
            $deadLetters,
            null,
        );

        $outputRows = [];
        foreach ($generator as $row) {
            $outputRows[] = $row;
        }

        // Expected: all rows NOT in failingIndices, in original order
        $expectedRows = array_values(array_filter(
            $rows,
            fn(array $row) => !in_array($row['__index'], $failingIndices, true)
        ));

        $this->assertCount(
            count($expectedRows),
            $outputRows,
            sprintf(
                'Skip strategy output count (%d) should equal non-failing row count (%d)',
                count($outputRows),
                count($expectedRows)
            )
        );

        // Verify order preservation
        for ($k = 0; $k < count($expectedRows); $k++) {
            $this->assertSame(
                $expectedRows[$k]['__index'],
                $outputRows[$k]['__index'],
                "Output row at position {$k} should match expected non-failing row order"
            );
        }

        // Verify no failing rows appear in output
        $outputIndices = array_map(fn(array $row) => $row['__index'], $outputRows);
        foreach ($failingIndices as $failIdx) {
            $this->assertNotContains(
                $failIdx,
                $outputIndices,
                "Failing row at index {$failIdx} should not appear in skip strategy output"
            );
        }
    }

    /**
     * Data provider for Property 3: Retry Strategy Invokes Stage N Times.
     *
     * Generates 50+ random configurations with varying maxAttempts.
     *
     * @return Generator
     */
    public static function retryStrategyProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(2, 10);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    '__index' => $j,
                    'id' => random_int(1, 99999),
                    'name' => 'item_' . random_int(1000, 9999),
                    'value' => random_int(-500, 500) / 10.0,
                    'active' => (bool)random_int(0, 1),
                ];
            }
            // Pick one row to always fail
            $failIndex = random_int(0, $rowCount - 1);
            $maxAttempts = random_int(1, 5);

            yield "rows={$rowCount},failAt={$failIndex},attempts={$maxAttempts},i={$i}" => [
                $rows,
                $failIndex,
                $maxAttempts,
            ];
        }
    }

    /**
     * Property 3: Retry Strategy Invokes Stage N Times
     *
     * For any row that causes an exception in a stage configured with the retry
     * error strategy and a maxAttempts of N, the stage SHALL be invoked exactly
     * N times for that row before the row is considered failed.
     *
     * **Validates: Requirements 1.5**
     */
    #[Test]
    #[DataProvider('retryStrategyProvider')]
    public function retryStrategyInvokesStageNTimes(array $rows, int $failIndex, int $maxAttempts): void
    {
        $invocationTracker = new \ArrayObject();
        $processor = $this->createConfigurableProcessor([$failIndex], $invocationTracker);
        $processor->withName('test-retry-stage');

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);
        $retryConfig = new RetryConfig($maxAttempts, 0); // 0ms backoff for speed

        $generator = $runner->run(
            $processor,
            $input,
            ErrorStrategy::Retry,
            $retryConfig,
            $logger,
            $deadLetters,
            null,
        );

        // Consume the generator fully
        $outputRows = [];
        foreach ($generator as $row) {
            $outputRows[] = $row;
        }

        // The failing row should have been invoked exactly maxAttempts times
        $this->assertSame(
            $maxAttempts,
            $invocationTracker[$failIndex] ?? 0,
            sprintf(
                'Retry strategy should invoke stage exactly %d times for failing row at index %d, got %d',
                $maxAttempts,
                $failIndex,
                $invocationTracker[$failIndex] ?? 0
            )
        );

        // The failing row should NOT appear in output (all attempts failed)
        $outputIndices = array_map(fn(array $row) => $row['__index'], $outputRows);
        $this->assertNotContains(
            $failIndex,
            $outputIndices,
            "Failing row at index {$failIndex} should not appear in output after exhausting retries"
        );

        // The failing row should be in the dead-letter collection
        $this->assertGreaterThanOrEqual(
            1,
            count($deadLetters),
            'Dead-letter collection should contain the failed row after retry exhaustion'
        );
    }

    /**
     * Data provider for Property 4: Log-and-Continue Preserves Subsequent Row Data.
     *
     * Generates 50+ random row sets with random failing indices.
     *
     * @return Generator
     */
    public static function logAndContinueProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(3, 25);
            $rows = [];
            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = [
                    '__index' => $j,
                    'id' => random_int(1, 99999),
                    'name' => 'item_' . random_int(1000, 9999),
                    'value' => random_int(-500, 500) / 10.0,
                    'active' => (bool)random_int(0, 1),
                ];
            }
            // Pick random subset of rows to fail
            $failCount = random_int(1, max(1, (int)($rowCount / 3)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failingIndices = array_slice($allIndices, 0, $failCount);

            yield "rows={$rowCount},fails={$failCount},i={$i}" => [$rows, $failingIndices];
        }
    }

    /**
     * Property 4: Log-and-Continue Preserves Subsequent Row Data
     *
     * For any sequence of rows where some rows cause exceptions in a stage
     * configured with log-and-continue, all non-failing rows SHALL pass through
     * with their original data unmodified, and the pipeline SHALL continue
     * processing after each failure.
     *
     * **Validates: Requirements 1.6**
     */
    #[Test]
    #[DataProvider('logAndContinueProvider')]
    public function logAndContinuePreservesSubsequentRowData(array $rows, array $failingIndices): void
    {
        $invocationTracker = new \ArrayObject();
        $processor = $this->createConfigurableProcessor($failingIndices, $invocationTracker);
        $processor->withName('test-log-continue-stage');

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $generator = $runner->run(
            $processor,
            $input,
            ErrorStrategy::LogAndContinue,
            null,
            $logger,
            $deadLetters,
            null,
        );

        $outputRows = [];
        foreach ($generator as $row) {
            $outputRows[] = $row;
        }

        // All rows should appear in output (non-failing pass through, failing rows are yielded with original data)
        $this->assertCount(
            count($rows),
            $outputRows,
            sprintf(
                'Log-and-continue should yield all rows (%d), got %d',
                count($rows),
                count($outputRows)
            )
        );

        // Non-failing rows must have their original data unmodified
        $nonFailingRows = array_filter(
            $rows,
            fn(array $row) => !in_array($row['__index'], $failingIndices, true)
        );

        foreach ($nonFailingRows as $expectedRow) {
            $index = $expectedRow['__index'];
            // Find this row in output
            $found = false;
            foreach ($outputRows as $outputRow) {
                if ($outputRow['__index'] === $index) {
                    $this->assertSame(
                        $expectedRow,
                        $outputRow,
                        "Non-failing row at index {$index} must pass through with original data unmodified"
                    );
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "Non-failing row at index {$index} must appear in output");
        }

        // Pipeline continues after each failure - verify all rows were attempted
        $attemptedIndices = array_keys($invocationTracker->getArrayCopy());
        foreach (range(0, count($rows) - 1) as $expectedIndex) {
            $this->assertContains(
                $expectedIndex,
                $attemptedIndices,
                "Pipeline must continue processing after failure - row {$expectedIndex} should have been attempted"
            );
        }
    }
}
