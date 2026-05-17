<?php

namespace Simsoft\DataFlow\Tests\Transformers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\Chunk;

/**
 * ChunkTest class.
 *
 * Tests for the Chunk transformer batching behavior.
 */
#[CoversClass(Chunk::class)]
class ChunkTest extends TestCase
{
    /**
     * Test chunking with exact division (no remainder).
     * Validates: Requirements 10.1
     */
    #[Test]
    public function chunkWithExactDivision(): void
    {
        $chunk = new Chunk(2);
        $dataFrame = $this->arrayToIterator([1, 2, 3, 4, 5, 6]);

        $result = $this->iteratorToArray($chunk($dataFrame));

        $this->assertCount(3, $result);
        $this->assertSame([1, 2], $result[0]);
        $this->assertSame([3, 4], $result[1]);
        $this->assertSame([5, 6], $result[2]);
    }

    /**
     * Test chunking with remainder chunk (not evenly divisible).
     * Validates: Requirements 10.2
     */
    #[Test]
    public function chunkWithRemainder(): void
    {
        $chunk = new Chunk(2);
        $dataFrame = $this->arrayToIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray($chunk($dataFrame));

        $this->assertCount(3, $result);
        $this->assertSame([1, 2], $result[0]);
        $this->assertSame([3, 4], $result[1]);
        $this->assertSame([5], $result[2]);
    }

    /**
     * Test chunking when chunk size equals total item count (single chunk).
     * Validates: Requirements 10.3
     */
    #[Test]
    public function chunkSizeEqualsItemCount(): void
    {
        $chunk = new Chunk(5);
        $dataFrame = $this->arrayToIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray($chunk($dataFrame));

        $this->assertCount(1, $result);
        $this->assertSame([1, 2, 3, 4, 5], $result[0]);
    }

    /**
     * Test chunking when chunk size is larger than total item count.
     * Validates: Requirements 10.4
     */
    #[Test]
    public function chunkSizeLargerThanItemCount(): void
    {
        $chunk = new Chunk(10);
        $dataFrame = $this->arrayToIterator([1, 2, 3]);

        $result = $this->iteratorToArray($chunk($dataFrame));

        $this->assertCount(1, $result);
        $this->assertSame([1, 2, 3], $result[0]);
    }

    /**
     * Test chunking with null dataframe yields no items.
     * Validates: Requirements 10.5
     */
    #[Test]
    public function nullDataFrameYieldsNothing(): void
    {
        $chunk = new Chunk(3);

        $result = $this->iteratorToArray($chunk(null));

        $this->assertCount(0, $result);
    }
}
