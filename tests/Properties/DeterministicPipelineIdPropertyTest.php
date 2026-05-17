<?php

namespace Simsoft\DataFlow\Tests\Properties;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\CheckpointManager;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * DeterministicPipelineIdPropertyTest class
 *
 * Feature: enterprise-resilience, Property 12: Deterministic pipeline ID
 *
 * Property-based test verifying that for any set of pipeline stages,
 * calling generatePipelineId() multiple times with the same stage configuration
 * always produces the same pipeline ID string.
 *
 * **Validates: Requirements 5.4**
 */
#[CoversClass(CheckpointManager::class)]
class DeterministicPipelineIdPropertyTest extends TestCase
{
    private const ITERATIONS = 100;

    /**
     * Generate a random string of given length using alphanumeric + special characters.
     */
    private static function randomStageName(): string
    {
        $length = random_int(1, 50);
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-. ';
        $name = '';
        for ($i = 0; $i < $length; $i++) {
            $name .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $name;
    }

    /**
     * Generate a random array of stage names with length 1-10.
     *
     * @return array<int, string>
     */
    private static function randomStageArray(): array
    {
        $count = random_int(1, 10);
        $stages = [];
        for ($i = 0; $i < $count; $i++) {
            $stages[] = self::randomStageName();
        }
        return $stages;
    }

    /**
     * Data provider generating 100 random stage arrays for determinism tests.
     *
     * @return Generator
     */
    public static function deterministicIdProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $stages = self::randomStageArray();

            yield "stages=" . count($stages) . ",i={$i}" => [$stages];
        }
    }

    /**
     * Data provider generating 100 pairs of different stage arrays for collision resistance tests.
     *
     * @return Generator
     */
    public static function collisionResistanceProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $stagesA = self::randomStageArray();

            // Generate a different array by either changing content or length
            do {
                $stagesB = self::randomStageArray();
            } while ($stagesA === $stagesB);

            yield "i={$i}" => [$stagesA, $stagesB];
        }
    }

    /**
     * Data provider generating 100 random stage arrays for format validation.
     *
     * @return Generator
     */
    public static function sha256FormatProvider(): Generator
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $stages = self::randomStageArray();

            yield "stages=" . count($stages) . ",i={$i}" => [$stages];
        }
    }

    /**
     * Property 12a: Determinism — same input always produces same output.
     *
     * For any array of stage names, generatePipelineId() called twice with the
     * same array returns the same result.
     *
     * **Validates: Requirements 5.4**
     */
    #[Test]
    #[DataProvider('deterministicIdProvider')]
    public function generatePipelineIdIsDeterministic(array $stages): void
    {
        $result1 = CheckpointManager::generatePipelineId($stages);
        $result2 = CheckpointManager::generatePipelineId($stages);

        $this->assertSame(
            $result1,
            $result2,
            sprintf(
                'generatePipelineId() must be deterministic. Got "%s" and "%s" for stages: %s',
                $result1,
                $result2,
                json_encode($stages),
            ),
        );
    }

    /**
     * Property 12b: Collision resistance — different inputs produce different outputs.
     *
     * For any two different arrays of stage names, generatePipelineId() returns
     * different results.
     *
     * **Validates: Requirements 5.4**
     */
    #[Test]
    #[DataProvider('collisionResistanceProvider')]
    public function generatePipelineIdCollisionResistance(array $stagesA, array $stagesB): void
    {
        $resultA = CheckpointManager::generatePipelineId($stagesA);
        $resultB = CheckpointManager::generatePipelineId($stagesB);

        $this->assertNotSame(
            $resultA,
            $resultB,
            sprintf(
                'generatePipelineId() should produce different IDs for different inputs. '
                . 'Both returned "%s" for stages A: %s and stages B: %s',
                $resultA,
                json_encode($stagesA),
                json_encode($stagesB),
            ),
        );
    }

    /**
     * Property 12c: SHA-256 format — result is always a 64-character hex string.
     *
     * For any array of stage names, generatePipelineId() returns a string that
     * is exactly 64 characters long and contains only hexadecimal characters.
     *
     * **Validates: Requirements 5.4**
     */
    #[Test]
    #[DataProvider('sha256FormatProvider')]
    public function generatePipelineIdReturnsSha256Format(array $stages): void
    {
        $result = CheckpointManager::generatePipelineId($stages);

        $this->assertSame(
            64,
            strlen($result),
            sprintf(
                'Pipeline ID must be 64 characters (SHA-256 hex), got %d characters: "%s"',
                strlen($result),
                $result,
            ),
        );

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $result,
            sprintf(
                'Pipeline ID must be a lowercase hex string, got: "%s"',
                $result,
            ),
        );
    }
}
