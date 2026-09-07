<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Tests\Integration;

use Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Simsoft\DataFlow\CallableProcessor;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\ErrorStrategy;
use Simsoft\DataFlow\PipelineResult;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * MemoryScalingTest class.
 *
 * The README claims a "constant memory footprint regardless of dataset size".
 * These tests hold that claim to account by running the same pipeline over
 * datasets that differ by an order of magnitude and asserting that peak memory
 * does not grow with the row count.
 *
 * They assert a *ceiling*, not an exact figure: allocator behaviour varies
 * between PHP versions and platforms, so an exact byte count would be brittle.
 * The ceilings below are far under what a buffering implementation would use —
 * materialising 100k rows costs tens of megabytes, so a regression that starts
 * accumulating rows fails these loudly rather than marginally.
 *
 * Sizes are kept modest so the suite stays fast; the scaling behaviour is
 * visible well before a million rows.
 */
#[Group('memory')]
class MemoryScalingTest extends TestCase
{
    /** @var int Rows in the small run of each comparison. */
    private const int SMALL = 1_000;

    /** @var int Rows in the large run — 100x the small one. */
    private const int LARGE = 100_000;

    /**
     * Generate rows lazily, so the source itself never holds the dataset.
     *
     * @param int $count Number of rows to yield.
     * @return Generator<int, array{id: int, name: string, email: string, age: int}>
     */
    private function rows(int $count): Generator
    {
        for ($i = 0; $i < $count; $i++) {
            yield [
                'id' => $i,
                'name' => 'User ' . $i,
                'email' => "user{$i}@example.com",
                'age' => $i % 80,
            ];
        }
    }

    /**
     * Measure peak memory allocated while running the callback.
     *
     * PHP's peak is process-wide and monotonic, so it is reset first — otherwise
     * an earlier test's allocation would be reported as this one's.
     *
     * @param callable(): void $callback The work to measure.
     * @return int Peak bytes allocated during the callback.
     */
    private function peakBytesDuring(callable $callback): int
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $before = memory_get_usage();

        $callback();

        return memory_get_peak_usage() - $before;
    }

    #[Test]
    public function streamingPipelineMemoryDoesNotGrowWithRowCount(): void
    {
        $run = function (int $count): int {
            $sum = 0;

            $result = (new DataFlow())
                ->from($this->rows($count))
                ->filter(fn(array $row): bool => $row['age'] > 10)
                ->map(['label' => fn(array $row): string => $row['name'] . ' <' . $row['email'] . '>'])
                ->transform(fn(array $row): array => $row)
                ->load(function (array $row) use (&$sum): void {
                    $sum += $row['age'];
                })
                ->run();

            return $result->getProcessedRows();
        };

        // Warm up so autoloading and one-off allocations are not charged to the
        // small run, which would mask growth in the large one.
        $run(100);

        $smallPeak = $this->peakBytesDuring(fn() => $run(self::SMALL));
        $largePeak = $this->peakBytesDuring(fn() => $run(self::LARGE));

        $this->assertLessThan(
            1024 * 1024,
            $largePeak,
            sprintf(
                'Streaming %d rows should stay well under 1 MB; used %.1f KB',
                self::LARGE,
                $largePeak / 1024
            )
        );

        // The real assertion: 100x the rows must not cost meaningfully more
        // memory. A generous multiplier keeps this robust against allocator
        // noise while still failing hard if rows start accumulating.
        $this->assertLessThan(
            max($smallPeak * 4, 256 * 1024),
            $largePeak,
            sprintf(
                'Peak memory grew with dataset size: %.1f KB for %d rows vs %.1f KB for %d rows',
                $smallPeak / 1024,
                self::SMALL,
                $largePeak / 1024,
                self::LARGE
            )
        );
    }

    #[Test]
    public function chunkingHoldsOnlyOneChunkInMemory(): void
    {
        $run = function (int $count): int {
            $rows = 0;

            (new DataFlow())
                ->from($this->rows($count))
                ->chunk(100)
                ->load(function (array $chunk) use (&$rows): void {
                    $rows += count($chunk);
                })
                ->run();

            return $rows;
        };

        $run(100);

        $smallPeak = $this->peakBytesDuring(fn() => $run(self::SMALL));
        $largePeak = $this->peakBytesDuring(fn() => $run(self::LARGE));

        $this->assertLessThan(
            max($smallPeak * 4, 512 * 1024),
            $largePeak,
            sprintf(
                'chunk() should buffer one chunk, not the dataset: %.1f KB for %d rows vs %.1f KB for %d rows',
                $smallPeak / 1024,
                self::SMALL,
                $largePeak / 1024,
                self::LARGE
            )
        );
    }

    #[Test]
    public function limitDoesNotConsumeTheEntireSource(): void
    {
        // A source far larger than anything that would fit in the test's memory
        // budget if it were materialised.
        $peak = $this->peakBytesDuring(function (): void {
            $result = (new DataFlow())
                ->from($this->rows(1_000_000))
                ->limit(10)
                ->load(fn(array $row): array => $row)
                ->run();

            $this->assertSame(10, $result->getProcessedRows());
        });

        $this->assertLessThan(
            512 * 1024,
            $peak,
            sprintf('limit() must stop pulling from the source; used %.1f KB', $peak / 1024)
        );
    }

    /**
     * Run a pipeline in which every even-numbered row fails.
     *
     * @param int $count Rows to generate.
     * @param int|null $limit Dead-letter retention limit, or null for unlimited.
     * @return PipelineResult The completed pipeline result.
     */
    private function runWithFailures(int $count, ?int $limit): PipelineResult
    {
        return (new DataFlow())
            ->withDeadLetterLimit($limit)
            ->from($this->rows($count))
            ->transform(
                (new CallableProcessor(function (array $row): array {
                    if ($row['id'] % 2 === 0) {
                        throw new RuntimeException('rejected row ' . $row['id']);
                    }

                    return $row;
                }))->withErrorStrategy(ErrorStrategy::Skip)
            )
            ->load(fn(array $row): array => $row)
            ->run();
    }

    /**
     * The dead-letter cap is what keeps memory bounded when failures are bulk.
     *
     * Each retained failure holds the row, the exception, and its stack trace —
     * around 13 KB — so without a cap this scales linearly and defeats the
     * purpose of ErrorStrategy::Skip, which exists to keep long pipelines alive.
     *
     * @see docs/07-ERROR_HANDLING.md
     */
    #[Test]
    public function cappedDeadLettersKeepMemoryFlatAsFailuresGrow(): void
    {
        $this->runWithFailures(100, 100);

        $smallPeak = $this->peakBytesDuring(function (): void {
            $this->runWithFailures(1_000, 100);
        });

        $largePeak = $this->peakBytesDuring(function (): void {
            $this->runWithFailures(20_000, 100);
        });

        // 20x the failures, same retention, so memory must not follow.
        $this->assertLessThan(
            max($smallPeak * 4, 1024 * 1024),
            $largePeak,
            sprintf(
                'Capped dead letters should not scale with failures: %.1f KB for 500 vs %.1f KB for 10000',
                $smallPeak / 1024,
                $largePeak / 1024
            )
        );
    }

    #[Test]
    public function cappingRetentionDoesNotDistortTheFailureCount(): void
    {
        $result = $this->runWithFailures(2_000, 10);

        // The whole point of separating the two counts: the total stays exact
        // even though only 10 entries survive for inspection.
        $this->assertSame(1_000, $result->getFailedRows());
        $this->assertCount(10, $result->getDeadLetters());
        $this->assertCount(10, $result->getFailures());

        $deadLetters = $result->getDeadLetters();
        $this->assertSame(1_000, $deadLetters->totalCount());
        $this->assertSame(990, $deadLetters->droppedCount());
        $this->assertTrue($deadLetters->isTruncated());
    }

    #[Test]
    public function unlimitedRetentionStillGrowsWithFailureCount(): void
    {
        // Kept small deliberately: retention costs ~13 KB per failed row, so
        // this is the behaviour users opt into with null, and the reason the
        // default is capped.
        $smallPeak = $this->peakBytesDuring(function (): void {
            $this->runWithFailures(1_000, null);
        });

        $largePeak = $this->peakBytesDuring(function (): void {
            $this->runWithFailures(10_000, null);
        });

        $this->assertGreaterThan(
            $smallPeak * 3,
            $largePeak,
            sprintf(
                'Unlimited retention is expected to scale: %.1f KB for 500 vs %.1f KB for 5000',
                $smallPeak / 1024,
                $largePeak / 1024
            )
        );
    }
}
