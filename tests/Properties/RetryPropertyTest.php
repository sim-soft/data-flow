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
 * RetryPropertyTest class
 *
 * Property-based tests for retry behavior using randomized inputs.
 * Uses small delay values (1-5ms) to keep tests fast while still
 * verifying timing properties.
 */
#[CoversClass(StageRunner::class)]
class RetryPropertyTest extends TestCase
{
    /**
     * Data provider generating 20 random retry configurations for backoff delay tests.
     *
     * Each case provides a random maxAttempts (2-5) and delay (1-5ms) along with
     * random row data to process.
     *
     * @return Generator
     */
    public static function retryBackoffDelayProvider(): Generator
    {
        for ($i = 0; $i < 20; $i++) {
            $maxAttempts = random_int(2, 5);
            $delay = random_int(5, 10);
            $rowData = 'row_' . random_int(1000, 9999);

            yield "attempts={$maxAttempts},backoff={$delay}ms,i={$i}" => [
                $maxAttempts,
                $delay,
                $rowData,
            ];
        }
    }

    /**
     * Property 6: Retry Backoff Delay Is Applied
     *
     * For any retry configuration with delay of D and maxAttempts of N where all
     * attempts fail, the total elapsed time for processing that row SHALL be at least
     * (N - 1) × D milliseconds.
     *
     * **Validates: Requirements 3.3**
     *
     * @param int $maxAttempts The maximum number of retry attempts.
     * @param int $delay The backoff delay in milliseconds between attempts.
     * @param string $rowData The row data to process.
     * @return void
     */
    #[Test]
    #[DataProvider('retryBackoffDelayProvider')]
    public function retryBackoffDelayIsApplied(int $maxAttempts, int $delay, string $rowData): void
    {
        $stage = new class extends Processor {
            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        throw new RuntimeException("Transient failure for: {$row}");
                    }
                }
                return new ArrayIterator([]);
            }
        };
        $stage->withName('always-failing-stage');

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $retryConfig = new RetryConfig($maxAttempts, $delay);
        $input = new ArrayIterator([$rowData]);

        $startTime = hrtime(true);

        // Consume the generator to trigger processing
        $output = $runner->run(
            $stage,
            $input,
            ErrorStrategy::Retry,
            $retryConfig,
            $logger,
            $deadLetters,
            null,
        );

        // Exhaust the generator
        foreach ($output as $_) {
            // consume
        }

        $elapsedNs = hrtime(true) - $startTime;
        $elapsedMs = $elapsedNs / 1_000_000;

        // The minimum expected delay: (N - 1) backoff delays applied between attempts
        $expectedMinMs = ($maxAttempts - 1) * $delay;

        $this->assertGreaterThanOrEqual(
            $expectedMinMs,
            $elapsedMs,
            "Retry backoff delay not applied. Expected at least {$expectedMinMs}ms "
            . "(maxAttempts={$maxAttempts}, delay={$delay}), but elapsed was {$elapsedMs}ms"
        );
    }

    /**
     * Data provider generating 20 random retry exhaustion scenarios.
     *
     * Each case provides random row data, a random stage name, a random maxAttempts,
     * and a random row index to verify dead-letter collection entries.
     *
     * @return Generator
     */
    public static function retryExhaustionDeadLetterProvider(): Generator
    {
        for ($i = 0; $i < 20; $i++) {
            $maxAttempts = random_int(1, 5);
            $delay = random_int(1, 3);
            $rowData = ['id' => random_int(1, 10000), 'value' => 'data_' . random_int(100, 999)];
            $stageName = 'stage_' . random_int(1, 100);

            yield "attempts={$maxAttempts},stage={$stageName},i={$i}" => [
                $maxAttempts,
                $delay,
                $rowData,
                $stageName,
            ];
        }
    }

    /**
     * Property 7: Retry Exhaustion Adds to Dead-Letter Collection
     *
     * For any row that exhausts all retry attempts, the dead-letter collection SHALL
     * contain an entry with that row's data, the stage name, the row index, and the
     * final exception.
     *
     * **Validates: Requirements 3.4, 5.2**
     *
     * @param int $maxAttempts The maximum number of retry attempts.
     * @param int $delay The backoff delay in milliseconds.
     * @param array $rowData The row data to process.
     * @param string $stageName The name of the stage.
     * @return void
     */
    #[Test]
    #[DataProvider('retryExhaustionDeadLetterProvider')]
    public function retryExhaustionAddsToDeadLetterCollection(
        int    $maxAttempts,
        int    $delay,
        array  $rowData,
        string $stageName,
    ): void
    {
        $errorMessage = "Permanent failure for row id={$rowData['id']}";

        $stage = new class($errorMessage) extends Processor {
            public function __construct(private readonly string $errorMessage)
            {
                // No parent constructor to call
            }

            public function __invoke(?Iterator $dataFrame = null): Iterator
            {
                if ($dataFrame !== null) {
                    foreach ($dataFrame as $row) {
                        throw new RuntimeException($this->errorMessage);
                    }
                }
                return new ArrayIterator([]);
            }
        };
        $stage->withName($stageName);

        $runner = new StageRunner();
        $logger = new NullLogger();
        $deadLetters = new DeadLetterCollection();
        $retryConfig = new RetryConfig($maxAttempts, $delay);
        $input = new ArrayIterator([$rowData]);

        // Consume the generator to trigger processing
        $output = $runner->run(
            $stage,
            $input,
            ErrorStrategy::Retry,
            $retryConfig,
            $logger,
            $deadLetters,
            null,
        );

        // Exhaust the generator
        foreach ($output as $_) {
            // consume
        }

        // Verify dead-letter collection has exactly one entry
        $this->assertCount(1, $deadLetters, 'Dead-letter collection should contain exactly one entry');

        $entries = $deadLetters->toArray();
        $entry = $entries[0];

        // Verify the entry contains the correct row data
        $this->assertSame(
            $rowData,
            $entry->row,
            'Dead-letter entry row data should match the original row'
        );

        // Verify the entry contains the correct stage name
        $this->assertSame(
            $stageName,
            $entry->stageName,
            'Dead-letter entry stage name should match the configured stage name'
        );

        // Verify the entry contains the correct row index (1-based in StageRunner)
        $this->assertSame(
            1,
            $entry->rowIndex,
            'Dead-letter entry row index should be 1 (first row processed)'
        );

        // Verify the entry contains the final exception
        $this->assertInstanceOf(
            RuntimeException::class,
            $entry->exception,
            'Dead-letter entry should contain the final exception'
        );

        $this->assertSame(
            $errorMessage,
            $entry->exception->getMessage(),
            'Dead-letter entry exception message should match the thrown exception'
        );
    }
}
