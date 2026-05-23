<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
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
     * Run a Visualize loader against a dataframe and return the captured output.
     *
     * @param ArrayIterator<int|string, mixed> $dataFrame
     * @return array{output: string, result: array<int|string, mixed>}
     */
    private function runWithCapture(Visualize $visualize, ArrayIterator $dataFrame): array
    {
        $stream = fopen('php://memory', 'w+');
        $visualize = new Visualize($visualize::class === Visualize::class ? Visualize::FORMAT_JSON : Visualize::FORMAT_OBJ, $stream);
        // The above is a fallback; actual instantiation happens in each test.
        // We rebuild using the format the caller wanted via reflection isn't worth it; just rebuild.
        return ['output' => '', 'result' => []];
    }

    /**
     * Build a Visualize loader writing to a memory stream and return the stream + loader.
     *
     * @return array{0: Visualize, 1: resource}
     */
    private function buildLoader(string $format): array
    {
        $stream = fopen('php://memory', 'w+');
        $visualize = new Visualize($format, $stream);
        return [$visualize, $stream];
    }

    private function captureOutput($stream): string
    {
        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);
        return $output;
    }

    /**
     * Test FORMAT_JSON outputs array data as JSON strings.
     */
    #[Test]
    public function formatJsonOutputsArrayDataAsJsonStrings(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_JSON);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $dataFrame = new ArrayIterator($data);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = $this->captureOutput($stream);

        $expectedOutput = json_encode(['id' => 1, 'name' => 'Alice']) . PHP_EOL
            . json_encode(['id' => 2, 'name' => 'Bob']) . PHP_EOL;

        $this->assertSame($expectedOutput, $output);
    }

    /**
     * Test FORMAT_OBJ outputs data via var_export-style dump.
     */
    #[Test]
    public function formatObjOutputsDataViaVarDump(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_OBJ);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
        ];
        $dataFrame = new ArrayIterator($data);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = $this->captureOutput($stream);

        // var_export output for an array
        $this->assertStringContainsString('array', $output);
        $this->assertStringContainsString("'id'", $output);
        $this->assertStringContainsString("'name'", $output);
        $this->assertStringContainsString('Alice', $output);
    }

    /**
     * Test FORMAT_JSON with non-array data falls back to var_export.
     */
    #[Test]
    public function formatJsonWithNonArrayDataUsesVarDump(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_JSON);
        $data = ['hello', 'world'];
        $dataFrame = new ArrayIterator($data);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = $this->captureOutput($stream);

        // Non-array scalar strings use var_export, which outputs single-quoted strings
        $this->assertStringContainsString("'hello'", $output);
        $this->assertStringContainsString("'world'", $output);
    }

    /**
     * Test items are yielded through (passthrough behavior).
     */
    #[Test]
    public function itemsAreYieldedThrough(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_JSON);
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Charlie'],
        ];
        $dataFrame = new ArrayIterator($data);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $this->captureOutput($stream);

        $this->assertSame($data, $result);
    }

    /**
     * Test nested Iterator data is output and yielded individually.
     */
    #[Test]
    public function nestedIteratorIsOutputAndYieldedIndividually(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_JSON);

        $nestedData = new ArrayIterator([
            ['id' => 10, 'name' => 'Nested1'],
            ['id' => 20, 'name' => 'Nested2'],
        ]);

        // The dataframe contains an Iterator as one of its items
        $dataFrame = new ArrayIterator([$nestedData]);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $output = $this->captureOutput($stream);

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
     */
    #[Test]
    public function passthroughPreservesKeys(): void
    {
        [$visualize, $stream] = $this->buildLoader(Visualize::FORMAT_JSON);
        $data = [
            'first' => ['id' => 1],
            'second' => ['id' => 2],
        ];
        $dataFrame = new ArrayIterator($data);

        $result = $this->iteratorToArray($visualize($dataFrame));
        $this->captureOutput($stream);

        $this->assertArrayHasKey('first', $result);
        $this->assertArrayHasKey('second', $result);
        $this->assertSame(['id' => 1], $result['first']);
        $this->assertSame(['id' => 2], $result['second']);
    }
}
