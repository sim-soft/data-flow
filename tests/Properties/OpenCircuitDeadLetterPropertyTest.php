<?php

namespace Simsoft\DataFlow\Tests\Properties;

use ArrayIterator;
use Generator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\CircuitBreaker;
use Simsoft\DataFlow\CircuitBreakerConfig;
use Simsoft\DataFlow\DeadLetterCollection;
use Simsoft\DataFlow\DeadLetterEntry;
use Simsoft\DataFlow\Enums\CircuitState;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Processor;
use Simsoft\DataFlow\StageRunner;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * OpenCircuitDeadLetterPropertyTest
 *
 * Property-based test verifying that rows skipped due to an Open circuit breaker
 * are recorded in the DeadLetterCollection with a "circuit-open" indication.
 *
 * Feature: enterprise-resilience, Property 9: Open circuit records skipped rows in dead letters
 */
#[CoversClass(StageRunner::class)]
#[CoversClass(CircuitBreaker::class)]
#[CoversClass(DeadLetterCollection::class)]
#[CoversClass(DeadLetterEntry::class)]
class OpenCircuitDeadLetterPropertyTest extends TestCase
{
    /**
     * Data provider generating 100 random configurations for circuit breaker
     * dead letter recording tests.
     *
     * Each case provides:
     * - failureThreshold: random 1-5 (number of failures to open the circuit)
     * - totalRows: random 5-20 (total rows to send through the stage)
     *
     * @return Generator
     */
    public static function openCircuitDeadLetterProvider(): Generator
    {
        for ($i = 0; $i < 100; $i++) {
            $failureThreshold = random_int(1, 5);
            $totalRows = random_int(5, 20);

            yield "threshold={$failureThreshold},rows={$totalRows},i={$i}" => [
                $failureThreshold,
                $totalRows,
            ];
        }
    }

    /**
     * Property 9: Open circuit records skipped rows in dead letters
     *
     * For any row skipped due to an Open circuit breaker, the row SHALL appear
     * in the DeadLetterCollection with a circuit-open indication.
     *
     * Strategy:
     * 1. Create a Transformer that always throws (to trigger failures and open the circuit)
     * 2. Configure a circuit breaker with the given failureThreshold
     * 3. Send totalRows through the StageRunner with Skip error strategy
     * 4. The first `failureThreshold` rows will fail and open the circuit
     * 5. All subsequent rows should be skipped due to open circuit and recorded as dead letters
     *    with exception message "circuit-open"
     *
     * **Validates: Requirements 4.6**
     */
    #[Test]
    #[DataProvider('openCircuitDeadLetterProvider')]
    public function openCircuitRecordsSkippedRowsInDeadLetters(
        int $failureThreshold,
        int $totalRows,
    ): void
    {
        $stageName = 'test-circuit-stage-' . random_int(1, 9999);

        // Create a transformer that always throws — this will open the circuit
        $stage = $this->createAlwaysFailingProcessor($stageName, $failureThreshold);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $input = new ArrayIterator($this->generateRows($totalRows));

        // Run with Skip strategy so processing continues after failures
        $output = iterator_to_array(
            $runner->run($stage, $input, ErrorStrategy::Skip, null, $logger, $deadLetters, null),
            false,
        );

        // Calculate expected counts:
        // - First `failureThreshold` rows fail normally (recorded as regular dead letters)
        // - Remaining rows are skipped due to open circuit (recorded with "circuit-open")
        $rowsAfterCircuitOpen = $totalRows - $failureThreshold;
        $circuitOpenEntries = array_filter(
            $deadLetters->toArray(),
            fn(DeadLetterEntry $entry) => $entry->exception->getMessage() === 'circuit-open',
        );

        // Verify the count of circuit-open dead letters matches rows sent while circuit was open
        $this->assertCount(
            $rowsAfterCircuitOpen,
            $circuitOpenEntries,
            "Expected {$rowsAfterCircuitOpen} circuit-open dead letters, got " . count($circuitOpenEntries)
            . " (threshold={$failureThreshold}, totalRows={$totalRows})",
        );

        // Verify each circuit-open dead letter entry has correct properties
        foreach ($circuitOpenEntries as $entry) {
            $this->assertInstanceOf(DeadLetterEntry::class, $entry);

            // Must contain the original row data
            $this->assertNotNull($entry->row, 'Circuit-open dead letter must contain original row data');
            $this->assertIsArray($entry->row);
            $this->assertArrayHasKey('id', $entry->row);

            // Must reference the correct stage name
            $this->assertSame(
                $stageName,
                $entry->stageName,
                'Circuit-open dead letter must reference the correct stage name',
            );

            // Must have exception with message "circuit-open"
            $this->assertInstanceOf(RuntimeException::class, $entry->exception);
            $this->assertSame(
                'circuit-open',
                $entry->exception->getMessage(),
                'Circuit-open dead letter must have exception message "circuit-open"',
            );

            // Row index must be valid (greater than failureThreshold since those are the skipped ones)
            $this->assertGreaterThan(
                $failureThreshold,
                $entry->rowIndex,
                'Circuit-open dead letter row index must be after the threshold rows',
            );
        }

        // Total dead letters = failures that opened circuit + circuit-open skips
        $this->assertCount(
            $totalRows,
            $deadLetters,
            "Total dead letters should equal total rows (all fail or get skipped): "
            . "expected {$totalRows}, got " . count($deadLetters),
        );

        // No output rows should be produced (all rows either fail or are skipped)
        $this->assertCount(
            0,
            $output,
            'No output rows should be produced when all rows fail or are circuit-open skipped',
        );
    }

    /**
     * Create a Processor that always throws exceptions.
     *
     * The processor is configured with a circuit breaker. It always throws,
     * which causes the circuit to open after failureThreshold failures.
     * Subsequent rows are then skipped by the StageRunner's circuit breaker logic.
     *
     * @param string $stageName The name to assign to the processor.
     * @param int $failureThreshold The circuit breaker failure threshold.
     * @return Processor
     */
    private function createAlwaysFailingProcessor(string $stageName, int $failureThreshold): Processor
    {
        $processor = new class extends Processor {
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
                    throw new RuntimeException('Simulated stage failure');
                }
            }
        };

        $processor->withName($stageName);
        $processor->withCircuitBreaker(failureThreshold: $failureThreshold, cooldownMs: 60000);

        return $processor;
    }

    /**
     * Generate an array of test rows with sequential IDs.
     *
     * @param int $count Number of rows to generate.
     * @return array<int, array{id: int, value: string}>
     */
    private function generateRows(int $count): array
    {
        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $rows[] = ['id' => $i, 'value' => 'row_' . random_int(100, 9999)];
        }
        return $rows;
    }
}
