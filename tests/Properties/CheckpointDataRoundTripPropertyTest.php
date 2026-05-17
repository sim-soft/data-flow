<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CheckpointData;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * CheckpointDataRoundTripPropertyTest class
 *
 * Feature: enterprise-resilience, Property 11: Checkpoint data round-trip serialization
 *
 * Property-based test verifying that for any valid CheckpointData object,
 * serializing to JSON via toJson() and deserializing back via fromJson()
 * produces an equivalent CheckpointData object with identical properties.
 *
 * **Validates: Requirements 5.3**
 */
#[CoversClass(CheckpointData::class)]
class CheckpointDataRoundTripPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Data provider generating 100 random CheckpointData configurations.
     *
     * Each case provides:
     * - pipelineId: random alphanumeric string (8-32 chars)
     * - lastRowIndex: random int in [0, 100000]
     * - timestamp: random int (Unix timestamp range)
     * - stageName: random alphanumeric string (3-20 chars)
     *
     * @return Generator
     */
    public static function checkpointDataProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $pipelineId = self::randomString(random_int(8, 32));
            $lastRowIndex = random_int(0, 100000);
            $timestamp = random_int(1000000000, 2000000000);
            $stageName = self::randomString(random_int(3, 20));

            yield "pipeline={$pipelineId},row={$lastRowIndex},ts={$timestamp},stage={$stageName},i={$i}" => [
                $pipelineId,
                $lastRowIndex,
                $timestamp,
                $stageName,
            ];
        }
    }

    /**
     * Generate a random alphanumeric string of the given length.
     */
    private static function randomString(int $length): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        $max = strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, $max)];
        }

        return $result;
    }

    /**
     * Property 11: Checkpoint data round-trip serialization
     *
     * For any valid CheckpointData object, serializing to JSON and deserializing back
     * SHALL produce an equivalent CheckpointData object with identical pipelineId,
     * lastRowIndex, timestamp, and stageName.
     *
     * **Validates: Requirements 5.3**
     */
    #[Test]
    #[DataProvider('checkpointDataProvider')]
    public function checkpointDataRoundTripSerialization(
        string $pipelineId,
        int    $lastRowIndex,
        int    $timestamp,
        string $stageName,
    ): void
    {
        // Create original CheckpointData
        $original = new CheckpointData(
            pipelineId: $pipelineId,
            lastRowIndex: $lastRowIndex,
            timestamp: $timestamp,
            stageName: $stageName,
        );

        // Serialize to JSON
        $json = $original->toJson();

        // Deserialize back
        $restored = CheckpointData::fromJson($json);

        // fromJson must not return null for valid data
        $this->assertNotNull(
            $restored,
            "fromJson() returned null for valid JSON: {$json}"
        );

        // All properties must match exactly after round-trip
        $this->assertSame(
            $original->pipelineId,
            $restored->pipelineId,
            "pipelineId mismatch after round-trip: expected '{$original->pipelineId}', got '{$restored->pipelineId}'"
        );

        $this->assertSame(
            $original->lastRowIndex,
            $restored->lastRowIndex,
            "lastRowIndex mismatch after round-trip: expected {$original->lastRowIndex}, got {$restored->lastRowIndex}"
        );

        $this->assertSame(
            $original->timestamp,
            $restored->timestamp,
            "timestamp mismatch after round-trip: expected {$original->timestamp}, got {$restored->timestamp}"
        );

        $this->assertSame(
            $original->stageName,
            $restored->stageName,
            "stageName mismatch after round-trip: expected '{$original->stageName}', got '{$restored->stageName}'"
        );
    }
}
