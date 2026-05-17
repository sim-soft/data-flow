<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\PipelineResult;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformer;

/**
 * DryRunPropertyTest
 *
 * Property-based tests for dry-run mode behavior.
 * Uses randomized data across 50+ iterations to verify universal properties.
 *
 * **Validates: Requirements 12.2, 12.3, 12.5**
 */
#[CoversClass(DataFlow::class)]
#[CoversClass(PipelineResult::class)]
class DryRunPropertyTest extends TestCase
{
    /**
     * Generate a random row with an index marker.
     *
     * @param int $index The row index.
     * @return array<string, mixed>
     */
    private static function generateRandomRow(int $index): array
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
     * Generate a random set of rows.
     *
     * @param int $count Number of rows to generate.
     * @return array<int, array<string, mixed>>
     */
    private static function generateRandomRows(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = self::generateRandomRow($i);
        }
        return $rows;
    }

    /**
     * Data provider for Property 17: Dry-Run Equivalence for Non-Loader Stages.
     *
     * Generates 50+ random row sets with varying sizes and transformer logic.
     *
     * @return Generator
     */
    public static function dryRunEquivalenceProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(3, 30);
            $rows = self::generateRandomRows($rowCount);

            yield "rows={$rowCount},i={$i}" => [$rows];
        }
    }

    /**
     * Property 17: Dry-Run Equivalence for Non-Loader Stages
     *
     * For any pipeline in dry-run mode, extractors and transformers SHALL produce
     * identical output rows as they would in normal mode, and the PipelineResult
     * row counts SHALL reflect the rows that would have been written.
     *
     * **Validates: Requirements 12.2, 12.5**
     */
    #[Test]
    #[DataProvider('dryRunEquivalenceProvider')]
    public function dryRunEquivalenceForNonLoaderStages(array $rows): void
    {
        // Track rows received by the loader in normal mode
        $normalLoaderRows = [];
        $normalLoader = new class ($normalLoaderRows) extends Loader {
            /** @var array<int, mixed> */
            private array $receivedRows;

            public function __construct(array &$receivedRows)
            {
                $this->receivedRows = &$receivedRows;
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                $output = [];
                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        $this->receivedRows[] = $row;
                        // Simulate a write operation
                        $output[] = $row;
                    }
                }
                return new ArrayIterator($output);
            }
        };

        // Track rows received by the loader in dry-run mode
        $dryRunLoaderRows = [];
        $dryRunLoader = new class ($dryRunLoaderRows) extends Loader {
            /** @var array<int, mixed> */
            private array $receivedRows;

            public function __construct(array &$receivedRows)
            {
                $this->receivedRows = &$receivedRows;
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                $output = [];
                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        $this->receivedRows[] = $row;
                        // In dry-run mode, skip actual writes but still yield rows
                        $output[] = $row;
                    }
                }
                return new ArrayIterator($output);
            }
        };

        // A simple transformer that doubles the 'value' field
        $transformer = new class () extends Transformer {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                $output = [];
                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        $row['value'] = $row['value'] * 2;
                        $output[] = $row;
                    }
                }
                return new ArrayIterator($output);
            }
        };

        // Run in normal mode
        $normalResult = (new DataFlow())
            ->from($rows)
            ->transform(clone $transformer)
            ->load($normalLoader)
            ->run();

        // Run in dry-run mode
        $dryRunResult = (new DataFlow())
            ->from($rows)
            ->transform(clone $transformer)
            ->load($dryRunLoader)
            ->dryRun()
            ->run();

        // The dry-run result MUST indicate it was a dry run
        $this->assertTrue(
            $dryRunResult->isDryRun(),
            'PipelineResult must indicate dry-run mode'
        );

        // Normal result must NOT indicate dry run
        $this->assertFalse(
            $normalResult->isDryRun(),
            'Normal PipelineResult must not indicate dry-run mode'
        );

        // The loader in dry-run mode receives the same rows as in normal mode
        // (extractors and transformers produce identical output)
        $this->assertCount(
            count($normalLoaderRows),
            $dryRunLoaderRows,
            'Dry-run loader must receive the same number of rows as normal loader'
        );

        for ($k = 0; $k < count($normalLoaderRows); $k++) {
            $this->assertSame(
                $normalLoaderRows[$k],
                $dryRunLoaderRows[$k],
                "Dry-run loader row at position {$k} must be identical to normal mode"
            );
        }

        // PipelineResult row counts SHALL reflect the rows that would have been written
        $this->assertSame(
            $normalResult->getProcessedRows(),
            $dryRunResult->getProcessedRows(),
            'Dry-run processedRows must equal normal mode processedRows'
        );
    }

    /**
     * Data provider for Property 18: Dry-Run Suppresses Loader Side Effects.
     *
     * Generates 50+ random row sets.
     *
     * @return Generator
     */
    public static function dryRunSuppressesSideEffectsProvider(): Generator
    {
        for ($i = 0; $i < 50; $i++) {
            $rowCount = random_int(1, 30);
            $rows = self::generateRandomRows($rowCount);

            yield "rows={$rowCount},i={$i}" => [$rows];
        }
    }

    /**
     * Property 18: Dry-Run Suppresses Loader Side Effects
     *
     * For any loader stage in dry-run mode, the loader SHALL receive all rows
     * (its __invoke is called) but SHALL NOT perform actual write operations
     * (file I/O, database inserts, API calls).
     *
     * **Validates: Requirements 12.3**
     */
    #[Test]
    #[DataProvider('dryRunSuppressesSideEffectsProvider')]
    public function dryRunSuppressesLoaderSideEffects(array $rows): void
    {
        $invokeCallCount = 0;
        $rowsReceived = [];
        $writeOperationsPerformed = 0;

        // Create a loader that tracks invocations and checks isDryRun() before writing
        $loader = new class ($invokeCallCount, $rowsReceived, $writeOperationsPerformed) extends Loader {
            private int $invokeCount;
            /** @var array<int, mixed> */
            private array $received;
            private int $writeOps;

            public function __construct(int &$invokeCount, array &$received, int &$writeOps)
            {
                $this->invokeCount = &$invokeCount;
                $this->received = &$received;
                $this->writeOps = &$writeOps;
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                $this->invokeCount++;
                $output = [];

                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        $this->received[] = $row;

                        if (!$this->isDryRun()) {
                            // Simulate actual write operation
                            $this->writeOps++;
                        }

                        $output[] = $row;
                    }
                }

                return new ArrayIterator($output);
            }
        };

        // Run in dry-run mode
        $result = (new DataFlow())
            ->from($rows)
            ->load($loader)
            ->dryRun()
            ->run();

        // The loader's __invoke MUST be called (loader receives rows)
        $this->assertGreaterThanOrEqual(
            1,
            $invokeCallCount,
            'Loader __invoke must be called in dry-run mode'
        );

        // The loader MUST receive all rows
        $this->assertCount(
            count($rows),
            $rowsReceived,
            sprintf(
                'Loader must receive all %d rows in dry-run mode, got %d',
                count($rows),
                count($rowsReceived)
            )
        );

        // Verify each row was received with correct data
        for ($k = 0; $k < count($rows); $k++) {
            $this->assertSame(
                $rows[$k]['__index'],
                $rowsReceived[$k]['__index'],
                "Loader must receive row at index {$k} in order"
            );
        }

        // Write operations MUST NOT be performed in dry-run mode
        $this->assertSame(
            0,
            $writeOperationsPerformed,
            'Loader must NOT perform write operations in dry-run mode (isDryRun() should return true)'
        );

        // The PipelineResult must indicate dry-run mode
        $this->assertTrue(
            $result->isDryRun(),
            'PipelineResult must indicate dry-run mode'
        );

        // The PipelineResult row counts must reflect what would have been written
        $this->assertSame(
            count($rows),
            $result->getProcessedRows(),
            'PipelineResult processedRows must reflect rows that would have been written'
        );
    }
}
