<?php

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Loaders\Visualize;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * VisualizeTest class.
 *
 * Tests for the Visualize loader.
 */
#[CoversClass(Visualize::class)]
class VisualizeTest extends TestCase
{
    /**
     * Test FORMAT_JSON outputs array data as JSON strings.
     *
     * Validates: Requirements 14.1
     */
    #[Test]
    public function formatJsonOutputsArrayDataAsJsonStrings(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_JSON);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $dataFrame = new ArrayIterator($data);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = ob_get_clean();

        $expectedOutput = json_encode(['id' => 1, 'name' => 'Alice']) . PHP_EOL
            . json_encode(['id' => 2, 'name' => 'Bob']) . PHP_EOL;

        $this->assertSame($expectedOutput, $output);
    }

    /**
     * Test FORMAT_OBJ outputs data via var_dump.
     *
     * Validates: Requirements 14.2
     */
    #[Test]
    public function formatObjOutputsDataViaVarDump(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_OBJ);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
        ];
        $dataFrame = new ArrayIterator($data);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = ob_get_clean();

        // var_dump output for an array
        $this->assertStringContainsString('array', $output);
        $this->assertStringContainsString('"id"', $output);
        $this->assertStringContainsString('"name"', $output);
        $this->assertStringContainsString('Alice', $output);
    }

    /**
     * Test FORMAT_JSON with non-array data falls back to var_dump.
     *
     * Validates: Requirements 14.2
     */
    #[Test]
    public function formatJsonWithNonArrayDataUsesVarDump(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_JSON);
        $data = ['hello', 'world'];
        $dataFrame = new ArrayIterator($data);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = ob_get_clean();

        // Non-array scalar strings use var_dump
        $this->assertStringContainsString('string', $output);
        $this->assertStringContainsString('hello', $output);
        $this->assertStringContainsString('world', $output);
    }

    /**
     * Test items are yielded through (passthrough behavior).
     *
     * Validates: Requirements 14.3
     */
    #[Test]
    public function itemsAreYieldedThrough(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_JSON);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];
        $dataFrame = new ArrayIterator($data);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        ob_end_clean();

        $this->assertSame($data, $result);
    }

    /**
     * Test nested Iterator data is output and yielded individually.
     *
     * Validates: Requirements 14.4
     */
    #[Test]
    public function nestedIteratorIsOutputAndYieldedIndividually(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_JSON);

        $nestedData = new ArrayIterator([
            ['id' => 10, 'name' => 'Nested1'],
            ['id' => 20, 'name' => 'Nested2'],
        ]);

        // The dataframe contains an Iterator as one of its items
        $dataFrame = new ArrayIterator([$nestedData]);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = ob_get_clean();

        // Each nested item should be output as JSON
        $expectedOutput = json_encode(['id' => 10, 'name' => 'Nested1']) . PHP_EOL
            . json_encode(['id' => 20, 'name' => 'Nested2']) . PHP_EOL;

        $this->assertSame($expectedOutput, $output);

        // Nested items should be yielded individually with their original keys
        $this->assertSame(['id' => 10, 'name' => 'Nested1'], $result[0]);
        $this->assertSame(['id' => 20, 'name' => 'Nested2'], $result[1]);
    }

    /**
     * Test passthrough preserves keys.
     *
     * Validates: Requirements 14.3
     */
    #[Test]
    public function passthroughPreservesKeys(): void
    {
        $visualize = new Visualize(Visualize::FORMAT_JSON);
        $data = [
            'first' => ['id' => 1],
            'second' => ['id' => 2],
        ];
        $dataFrame = new ArrayIterator($data);

        ob_start();
        $result = $this->iteratorToArray($visualize($dataFrame));
        ob_end_clean();

        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('second', $result);
        $this->assertSame(['id' => 1], $result['first']);
        $this->assertSame(['id' => 2], $result['second']);
    }
}
