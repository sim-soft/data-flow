<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CheckpointData;
use Simsoft\DataFlow\CheckpointManager;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\PipelineExecutor;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * ResumeSkipsRowsPropertyTest class
 *
 * Feature: enterprise-resilience, Property 13: Resume skips correct number of rows
 *
 * Property-based test verifying that for any valid checkpoint with lastRowIndex=N,
 * resuming the pipeline skips exactly the first N rows and begins processing at row N+1.
 * The PipelineResult reports processedRows = totalRows - N.
 *
 * **Validates: Requirements 6.2**
 */
#[CoversClass(PipelineExecutor::class)]
#[CoversClass(DataFlow::class)]
#[CoversClass(CheckpointManager::class)]
class ResumeSkipsRowsPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random totalRows/lastRowIndex combinations.
     *
     * Each case provides:
     * - totalRows: random int in [10, 100]
     * - lastRowIndex: random int in [1, totalRows - 1]
     *
     * @return Generator
     */
    public static function resumeSkipProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $totalRows = random_int(10, 100);
            $lastRowIndex = random_int(1, $totalRows - 1);

            yield "totalRows={$totalRows},lastRowIndex={$lastRowIndex},i={$i}" => [
                $totalRows,
                $lastRowIndex,
            ];
        }
    }

    /**
     * Property 13: Resume skips correct number of rows
     *
     * For any valid checkpoint with lastRowIndex=N, resuming the pipeline
     * SHALL skip exactly the first N rows and begin processing at row N+1.
     * The PipelineResult reports processedRows = totalRows - lastRowIndex.
     *
     * **Validates: Requirements 6.2**
     */
    #[Test]
    #[DataProvider('resumeSkipProvider')]
    public function resumeSkipsCorrectNumberOfRows(
        int $totalRows,
        int $lastRowIndex,
    ): void
    {
        $checkpointPath = sys_get_temp_dir() . '/resume_skip_test_' . uniqid() . '.json';

        try {
            // Generate source data: rows indexed 1..totalRows
            $sourceData = [];
            for ($i = 1; $i <= $totalRows; $i++) {
                $sourceData[] = ['index' => $i];
            }

            // Compute the deterministic pipeline ID matching the stages:
            // IterableExtractor (from iterable) + CallableProcessor (from closure loader)
            $stageNames = [
                'Simsoft\\DataFlow\\Extractors\\IterableExtractor',
                'Simsoft\\DataFlow\\CallableProcessor',
            ];
            $pipelineId = hash('sha256', json_encode($stageNames, JSON_THROW_ON_ERROR));

            // Write a checkpoint file with the known lastRowIndex
            $checkpoint = new CheckpointData(
                pipelineId: $pipelineId,
                lastRowIndex: $lastRowIndex,
                timestamp: time(),
                stageName: $stageNames[1],
            );
            file_put_contents($checkpointPath, $checkpoint->toJson());

            // Run the pipeline with resume enabled
            // Use a large checkpoint interval to avoid checkpoint writes during test
            $result = (new DataFlow())
                ->from($sourceData)
                ->withCheckpoint($checkpointPath, $totalRows + 1)
                ->resume()
                ->load(function (mixed $row) {
                    return $row;
                })
                ->run();

            // Assert: PipelineResult reports processedRows = totalRows - lastRowIndex
            $expectedProcessedCount = $totalRows - $lastRowIndex;
            $this->assertSame(
                $expectedProcessedCount,
                $result->getProcessedRows(),
                "With totalRows={$totalRows} and lastRowIndex={$lastRowIndex}, "
                . "PipelineResult->getProcessedRows() should be {$expectedProcessedCount} "
                . "but got {$result->getProcessedRows()}"
            );

            // Assert: checkpoint file is deleted on successful completion
            $this->assertFileDoesNotExist(
                $checkpointPath,
                "Checkpoint file should be deleted after successful pipeline completion"
            );
        } finally {
            // Cleanup
            @unlink($checkpointPath);
            @unlink($checkpointPath . '.tmp');
        }
    }
}
