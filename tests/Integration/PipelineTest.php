<?php

namespace Simsoft\DataFlow\Tests\Integration;

use ArrayIterator;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Payload;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * PipelineTest class.
 *
 * Integration tests for end-to-end pipeline composition.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class PipelineTest extends TestCase
{
    #[Test]
    public function fullPipelineArrayExtractClosureTransformCollectorLoad(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice', 'age' => 30],
            ['id' => 2, 'name' => 'Bob', 'age' => 25],
            ['id' => 3, 'name' => 'Charlie', 'age' => 35],
        ];

        $collected = [];

        (new DataFlow())
            ->from($input)
            ->transform(function (mixed &$data) {
                $data['age_doubled'] = $data['age'] * 2;
                return $data;
            })
            ->load(function (mixed &$data) use (&$collected) {
                $collected[] = $data;
                return $data;
            })
            ->run();

        $this->assertCount(3, $collected);
        $this->assertSame(60, $collected[0]['age_doubled']);
        $this->assertSame(50, $collected[1]['age_doubled']);
        $this->assertSame(70, $collected[2]['age_doubled']);
        $this->assertSame('Alice', $collected[0]['name']);
        $this->assertSame('Bob', $collected[1]['name']);
        $this->assertSame('Charlie', $collected[2]['name']);
    }

    #[Test]
    public function chainedTransformersFilterMapChunk(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice', 'score' => 85],
            ['id' => 2, 'name' => 'Bob', 'score' => 45],
            ['id' => 3, 'name' => 'Charlie', 'score' => 92],
            ['id' => 4, 'name' => 'Diana', 'score' => 78],
            ['id' => 5, 'name' => 'Eve', 'score' => 30],
        ];

        $collected = [];

        (new DataFlow())
            ->from($input)
            ->filter(fn(mixed $row) => $row['score'] >= 70)
            ->map([
                'student' => 'name',
                'grade' => fn(array $row) => $row['score'] >= 90 ? 'A' : 'B',
            ])
            ->chunk(2)
            ->load(function (mixed &$data) use (&$collected) {
                $collected[] = $data;
                return $data;
            })
            ->run();

        // Filter passes: Alice(85), Charlie(92), Diana(78) — 3 items
        // After chunk(2): [[Alice, Charlie], [Diana]]
        $this->assertCount(2, $collected);
        $this->assertCount(2, $collected[0]);
        $this->assertCount(1, $collected[1]);

        // Verify mapping was applied
        $this->assertSame('Alice', $collected[0][0]['student']);
        $this->assertSame('B', $collected[0][0]['grade']);
        $this->assertSame('Charlie', $collected[0][1]['student']);
        $this->assertSame('A', $collected[0][1]['grade']);
        $this->assertSame('Diana', $collected[1][0]['student']);
        $this->assertSame('B', $collected[1][0]['grade']);
    }

    #[Test]
    public function signalStopInTransformerLimitsDownstream(): void
    {
        $input = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $collected = [];

        (new DataFlow())
            ->from($input)
            ->transform(function (mixed &$data) {
                if ($data > 3) {
                    return Signal::Stop;
                }
                return $data;
            })
            ->load(function (mixed &$data) use (&$collected) {
                $collected[] = $data;
                return $data;
            })
            ->run();

        // Only items 1, 2, 3 should pass through (stop at 4)
        $this->assertSame([1, 2, 3], $collected);
    }

    #[Test]
    public function signalNextSkipsItemsInLoaderOutput(): void
    {
        $input = [1, 2, 3, 4, 5, 6];

        $collected = [];

        (new DataFlow())
            ->from($input)
            ->transform(function (mixed &$data) {
                // Skip even numbers
                if ($data % 2 === 0) {
                    return Signal::Next;
                }
                return $data;
            })
            ->load(function (mixed &$data) use (&$collected) {
                $collected[] = $data;
                return $data;
            })
            ->run();

        // Only odd numbers should appear
        $this->assertSame([1, 3, 5], $collected);
    }

    #[Test]
    public function payloadSharedStateAcrossStages(): void
    {
        $payload = new Payload(['counter' => 0, 'total' => 0]);

        $input = [10, 20, 30];

        $collected = [];

        (new DataFlow())
            ->from($input)
            ->transform(function (mixed &$data) use ($payload) {
                $payload->counter = $payload->counter + 1;
                $payload->total = $payload->total + $data;
                return $data;
            })
            ->load(function (mixed &$data) use ($payload, &$collected) {
                $collected[] = [
                    'value' => $data,
                    'running_counter' => $payload->counter,
                    'running_total' => $payload->total,
                ];
                return $data;
            })
            ->run();

        // Verify payload state was shared and accumulated across stages
        $this->assertSame(3, $payload->counter);
        $this->assertSame(60, $payload->total);

        // Verify loader had access to payload state set by transformer
        $this->assertSame(1, $collected[0]['running_counter']);
        $this->assertSame(10, $collected[0]['running_total']);
        $this->assertSame(2, $collected[1]['running_counter']);
        $this->assertSame(30, $collected[1]['running_total']);
        $this->assertSame(3, $collected[2]['running_counter']);
        $this->assertSame(60, $collected[2]['running_total']);
    }

    #[Test]
    public function dataFlowAsSourceComposition(): void
    {
        $sourceFlow = (new DataFlow())
            ->from([1, 2, 3, 4, 5])
            ->transform(function (mixed &$data) {
                return $data * 10;
            });

        $collected = [];

        (new DataFlow())
            ->from($sourceFlow)
            ->filter(fn(mixed $value) => $value >= 30)
            ->load(function (mixed &$data) use (&$collected) {
                $collected[] = $data;
                return $data;
            })
            ->run();

        // Source produces [10, 20, 30, 40, 50], filter keeps >= 30
        $this->assertSame([30, 40, 50], $collected);
    }
}
