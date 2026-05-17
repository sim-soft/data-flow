<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CheckpointManager;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * CheckpointIntervalPropertyTest class
 *
 * Feature: enterprise-resilience, Property 10: Checkpoint interval fires at correct row indices
 *
 * Property-based test verifying that for any checkpoint interval N,
 * shouldWrite(rowIndex) returns true if and only if rowIndex is a positive multiple of N.
 *
 * **Validates: Requirements 5.2**
 */
#[CoversClass(CheckpointManager::class)]
class CheckpointIntervalPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random interval/rowIndex combinations.
     *
     * Each case provides:
     * - interval: random int in [1, 1000]
     * - rowIndex: random int in [0, 10000]
     *
     * @return Generator
     */
    public static function checkpointIntervalProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $interval = random_int(1, 1000);
            $rowIndex = random_int(0, 10000);

            yield "interval={$interval},rowIndex={$rowIndex},i={$i}" => [
                $interval,
                $rowIndex,
            ];
        }
    }

    /**
     * Property 10: Checkpoint interval fires at correct row indices
     *
     * For any checkpoint interval N, shouldWrite(rowIndex) returns true
     * if and only if rowIndex > 0 AND rowIndex % interval === 0.
     *
     * **Validates: Requirements 5.2**
     */
    #[Test]
    #[DataProvider('checkpointIntervalProvider')]
    public function checkpointIntervalFiresAtCorrectRowIndices(
        int $interval,
        int $rowIndex,
    ): void
    {
        $manager = new CheckpointManager(
            filePath: sys_get_temp_dir() . '/checkpoint_test.json',
            interval: $interval,
        );

        $result = $manager->shouldWrite($rowIndex);

        $expected = $rowIndex > 0 && $rowIndex % $interval === 0;

        $this->assertSame(
            $expected,
            $result,
            "shouldWrite({$rowIndex}) with interval={$interval} should return "
            . ($expected ? 'true' : 'false')
            . " but got " . ($result ? 'true' : 'false')
            . " (rowIndex > 0: " . ($rowIndex > 0 ? 'yes' : 'no')
            . ", rowIndex % interval === 0: " . ($rowIndex % $interval === 0 ? 'yes' : 'no') . ")"
        );
    }

    /**
     * Property 10 (edge case): shouldWrite(0) always returns false regardless of interval.
     *
     * **Validates: Requirements 5.2**
     */
    #[Test]
    #[DataProvider('intervalOnlyProvider')]
    public function shouldWriteZeroAlwaysReturnsFalse(int $interval): void
    {
        $manager = new CheckpointManager(
            filePath: sys_get_temp_dir() . '/checkpoint_test.json',
            interval: $interval,
        );

        $this->assertFalse(
            $manager->shouldWrite(0),
            "shouldWrite(0) must always return false regardless of interval={$interval}"
        );
    }

    /**
     * Data provider generating 100 random intervals for the zero-index edge case.
     *
     * @return Generator
     */
    public static function intervalOnlyProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $interval = random_int(1, 1000);

            yield "interval={$interval},i={$i}" => [$interval];
        }
    }
}
