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
use Simsoft\DataFlow\DeadLetterEntry;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\RetryConfig;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * FailureRecordPropertyTest
 *
 * Property-based tests for failure records and row count invariants.
 * Uses randomized data to verify properties hold across many inputs.
 *
 * - Property 8: Failure Records Contain Complete Metadata
 * - Property 9: Row Count Invariant
 */
#[CoversClass(StageRunner::class)]
#[CoversClass(DeadLetterCollection::class)]
#[CoversClass(DeadLetterEntry::class)]
class FailureRecordPropertyTest extends TestCase
{
    /**
     * Data provider generating 50+ random row sets with random failure patterns
     * for the Skip error strategy.
     *
     * @return Generator
     */
    public static function skipStrategyFailureProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(3, 20);
            $rows = [];
            $failIndices = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'value' => 'data_' . random_int(100, 9999)];
            }

            // Randomly select 1 to half the rows to fail
            $failCount = random_int(1, max(1, (int)floor($rowCount / 2)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failIndices = array_slice($allIndices, 0, $failCount);

            yield "rows={$rowCount},fails={$failCount},i={$i}" => [$rows, $failIndices];
        }
    }

    /**
     * Data provider generating 50+ random row sets with random failure patterns
     * for the Retry error strategy (all retries exhausted).
     *
     * @return Generator
     */
    public static function retryExhaustedFailureProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(3, 15);
            $rows = [];
            $failIndices = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'name' => 'item_' . random_int(0, 9999)];
            }

            // Randomly select 1 to a third of the rows to fail
            $failCount = random_int(1, max(1, (int)floor($rowCount / 3)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failIndices = array_slice($allIndices, 0, $failCount);

            $maxAttempts = random_int(1, 3);

            yield "rows={$rowCount},fails={$failCount},attempts={$maxAttempts},i={$i}" => [
                $rows,
                $failIndices,
                $maxAttempts,
            ];
        }
    }

    /**
     * Data provider generating 50+ random row sets with random failure patterns
     * for the LogAndContinue error strategy.
     *
     * @return Generator
     */
    public static function logAndContinueFailureProvider(): Generator
    {
        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(3, 20);
            $rows = [];
            $failIndices = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'score' => random_int(-100, 100)];
            }

            // Randomly select 1 to half the rows to fail
            $failCount = random_int(1, max(1, (int)floor($rowCount / 2)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failIndices = array_slice($allIndices, 0, $failCount);

            yield "rows={$rowCount},fails={$failCount},i={$i}" => [$rows, $failIndices];
        }
    }

    /**
     * Data provider generating 50+ random row sets for the row count invariant
     * across all strategies.
     *
     * @return Generator
     */
    public static function rowCountInvariantProvider(): Generator
    {
        $strategies = [ErrorStrategy::Skip, ErrorStrategy::LogAndContinue, ErrorStrategy::Throw];

        for ($i = 0; $i < 55; $i++) {
            $rowCount = random_int(3, 25);
            $rows = [];

            for ($j = 0; $j < $rowCount; $j++) {
                $rows[] = ['id' => $j, 'payload' => bin2hex(random_bytes(random_int(1, 8)))];
            }

            // Randomly select failure indices
            $failCount = random_int(1, max(1, (int)floor($rowCount / 2)));
            $allIndices = range(0, $rowCount - 1);
            shuffle($allIndices);
            $failIndices = array_slice($allIndices, 0, $failCount);

            $strategy = $strategies[random_int(0, 2)];

            yield "rows={$rowCount},fails={$failCount},strategy={$strategy->value},i={$i}" => [
                $rows,
                $failIndices,
                $strategy,
            ];
        }
    }

    /**
     * Property 8: Failure Records Contain Complete Metadata (Skip Strategy)
     *
     * For any row that fails processing under the skip strategy, the failure record
     * (dead-letter entry) SHALL contain the original row data, the stage name,
     * the exception, and the row index.
     *
     * **Validates: Requirements 4.1, 5.2**
     */
    #[Test]
    #[DataProvider('skipStrategyFailureProvider')]
    public function failureRecordsContainCompleteMetadataForSkipStrategy(
        array $rows,
        array $failIndices,
    ): void
    {
        $stageName = 'test-skip-stage-' . random_int(1, 999);
        $stage = $this->createFailingProcessor($rows, $failIndices, $stageName);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $output = iterator_to_array(
            $runner->run($stage, $input, ErrorStrategy::Skip, null, $logger, $deadLetters, null),
            false,
        );

        // Verify each dead-letter entry has complete metadata
        $this->assertCount(count($failIndices), $deadLetters);

        foreach ($deadLetters as $entry) {
            $this->assertInstanceOf(DeadLetterEntry::class, $entry);
            // Must contain original row data
            $this->assertNotNull($entry->row, 'Dead-letter entry must contain original row data');
            $this->assertIsArray($entry->row);
            $this->assertArrayHasKey('id', $entry->row);

            // Must contain stage name
            $this->assertSame(
                $stageName,
                $entry->stageName,
                'Dead-letter entry must contain the stage name',
            );

            // Must contain exception
            $this->assertInstanceOf(
                \Throwable::class,
                $entry->exception,
                'Dead-letter entry must contain the exception',
            );

            // Must contain row index (1-based in StageRunner)
            $this->assertGreaterThan(
                0,
                $entry->rowIndex,
                'Dead-letter entry must contain a valid row index',
            );

            // Verify the row data matches one of the failing rows
            $rowId = $entry->row['id'];
            $this->assertContains(
                $rowId,
                $failIndices,
                'Dead-letter entry row data must correspond to a failing row',
            );
        }
    }

    /**
     * Property 8: Failure Records Contain Complete Metadata (Retry Exhausted)
     *
     * For any row that exhausts all retry attempts, the failure record
     * (dead-letter entry) SHALL contain the original row data, the stage name,
     * the exception, and the row index.
     *
     * **Validates: Requirements 3.4, 5.2**
     */
    #[Test]
    #[DataProvider('retryExhaustedFailureProvider')]
    public function failureRecordsContainCompleteMetadataForRetryExhausted(
        array $rows,
        array $failIndices,
        int   $maxAttempts,
    ): void
    {
        $stageName = 'test-retry-stage-' . random_int(1, 999);
        $stage = $this->createFailingProcessor($rows, $failIndices, $stageName);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $retryConfig = new RetryConfig(maxAttempts: $maxAttempts, delay: 0);
        $input = new ArrayIterator($rows);

        $output = iterator_to_array(
            $runner->run($stage, $input, ErrorStrategy::Retry, $retryConfig, $logger, $deadLetters, null),
            false,
        );

        // All failing rows should end up in dead letters after retry exhaustion
        $this->assertCount(count($failIndices), $deadLetters);

        foreach ($deadLetters as $entry) {
            $this->assertInstanceOf(DeadLetterEntry::class, $entry);
            // Must contain original row data
            $this->assertNotNull($entry->row, 'Dead-letter entry must contain original row data');
            $this->assertIsArray($entry->row);

            // Must contain stage name
            $this->assertSame(
                $stageName,
                $entry->stageName,
                'Dead-letter entry must contain the stage name',
            );

            // Must contain exception
            $this->assertInstanceOf(
                \Throwable::class,
                $entry->exception,
                'Dead-letter entry must contain the exception',
            );

            // Must contain row index
            $this->assertGreaterThan(
                0,
                $entry->rowIndex,
                'Dead-letter entry must contain a valid row index',
            );

            // Verify the row data matches one of the failing rows
            $rowId = $entry->row['id'];
            $this->assertContains(
                $rowId,
                $failIndices,
                'Dead-letter entry row data must correspond to a failing row',
            );
        }
    }

    /**
     * Property 8: Failure Records Contain Complete Metadata (LogAndContinue Strategy)
     *
     * For any row that fails processing under the log-and-continue strategy,
     * the failure record (dead-letter entry) SHALL contain the original row data,
     * the stage name, the exception, and the row index.
     *
     * **Validates: Requirements 4.1, 5.2**
     */
    #[Test]
    #[DataProvider('logAndContinueFailureProvider')]
    public function failureRecordsContainCompleteMetadataForLogAndContinue(
        array $rows,
        array $failIndices,
    ): void
    {
        $stageName = 'test-log-continue-stage-' . random_int(1, 999);
        $stage = $this->createFailingProcessor($rows, $failIndices, $stageName);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);

        $output = iterator_to_array(
            $runner->run($stage, $input, ErrorStrategy::LogAndContinue, null, $logger, $deadLetters, null),
            false,
        );

        // Verify each dead-letter entry has complete metadata
        $this->assertCount(count($failIndices), $deadLetters);

        foreach ($deadLetters as $entry) {
            $this->assertInstanceOf(DeadLetterEntry::class, $entry);
            // Must contain original row data
            $this->assertNotNull($entry->row, 'Dead-letter entry must contain original row data');
            $this->assertIsArray($entry->row);

            // Must contain stage name
            $this->assertSame(
                $stageName,
                $entry->stageName,
                'Dead-letter entry must contain the stage name',
            );

            // Must contain exception
            $this->assertInstanceOf(
                \Throwable::class,
                $entry->exception,
                'Dead-letter entry must contain the exception',
            );

            // Must contain row index
            $this->assertGreaterThan(
                0,
                $entry->rowIndex,
                'Dead-letter entry must contain a valid row index',
            );
        }
    }

    /**
     * Property 9: Row Count Invariant
     *
     * For any pipeline execution:
     * - Skip strategy: output rows + dead letter count = input rows
     * - LogAndContinue: output rows = input rows (all rows pass through)
     * - Throw: execution stops at the first failure
     *
     * **Validates: Requirements 4.2, 4.3, 9.3, 9.4, 9.5**
     */
    #[Test]
    #[DataProvider('rowCountInvariantProvider')]
    public function rowCountInvariantHoldsAcrossStrategies(
        array         $rows,
        array         $failIndices,
        ErrorStrategy $strategy,
    ): void
    {
        $stageName = 'test-invariant-stage';
        $stage = $this->createFailingProcessor($rows, $failIndices, $stageName);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($rows);
        $inputCount = count($rows);

        $retryConfig = $strategy === ErrorStrategy::Retry
            ? new RetryConfig(maxAttempts: 1, delay: 0)
            : null;

        if ($strategy === ErrorStrategy::Throw) {
            // For Throw strategy, execution stops at first failure
            $outputRows = [];
            $exceptionThrown = false;

            try {
                foreach ($runner->run($stage, $input, $strategy, $retryConfig, $logger, $deadLetters, null) as $row) {
                    $outputRows[] = $row;
                }
            } catch (\Throwable) {
                $exceptionThrown = true;
            }

            // If there are failing rows, an exception must have been thrown
            if (count($failIndices) > 0) {
                $this->assertTrue(
                    $exceptionThrown,
                    'Throw strategy must propagate exception on first failure',
                );

                // Output rows should be less than input rows (stopped at failure)
                $this->assertLessThan(
                    $inputCount,
                    count($outputRows),
                    'Throw strategy must stop processing at first failure',
                );

                // Dead letters should be empty for throw strategy
                $this->assertCount(0, $deadLetters);
            }
        } elseif ($strategy === ErrorStrategy::Skip) {
            // For Skip strategy: output rows + dead letter count = input rows
            $outputRows = iterator_to_array(
                $runner->run($stage, $input, $strategy, $retryConfig, $logger, $deadLetters, null),
                false,
            );

            $outputCount = count($outputRows);
            $deadLetterCount = count($deadLetters);

            $this->assertSame(
                $inputCount,
                $outputCount + $deadLetterCount,
                "Skip strategy invariant violated: output ({$outputCount}) + dead letters ({$deadLetterCount}) != input ({$inputCount})",
            );
        } elseif ($strategy === ErrorStrategy::LogAndContinue) {
            // For LogAndContinue: output rows = input rows (all rows pass through)
            $outputRows = iterator_to_array(
                $runner->run($stage, $input, $strategy, $retryConfig, $logger, $deadLetters, null),
                false,
            );

            $outputCount = count($outputRows);

            $this->assertSame(
                $inputCount,
                $outputCount,
                "LogAndContinue strategy invariant violated: output ({$outputCount}) != input ({$inputCount})",
            );
        }
    }

    /**
     * Create a Processor that fails on specific row indices.
     *
     * The processor passes through rows normally except for rows whose 'id' field
     * matches one of the specified fail indices, which throw a RuntimeException.
     *
     * @param array $rows The full set of rows (used for reference).
     * @param array $failIndices The 'id' values of rows that should fail.
     * @param string $stageName The name to assign to the processor.
     * @return Processor
     */
    private function createFailingProcessor(array $rows, array $failIndices, string $stageName): Processor
    {
        $failSet = array_flip($failIndices);

        $processor = new class ($failSet) extends Processor {
            public function __construct(private readonly array $failSet)
            {
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                if ($dataFrame === null) {
                    return new ArrayIterator([]);
                }

                return $this->processRows($dataFrame);
            }

            private function processRows(Iterator $dataFrame): Generator
            {
                foreach ($dataFrame as $row) {
                    if (isset($this->failSet[$row['id']])) {
                        throw new RuntimeException("Simulated failure for row id={$row['id']}");
                    }
                    yield $row;
                }
            }
        };

        $processor->withName($stageName);

        return $processor;
    }
}
