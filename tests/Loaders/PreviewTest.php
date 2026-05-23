<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Loaders\Preview;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * PreviewTest class.
 */
#[CoversClass(Preview::class)]
class PreviewTest extends TestCase
{
    /**
     * Run a Preview loader against a dataframe and return the captured output.
     *
     * @param ArrayIterator<int|string, mixed> $dataFrame
     * @return array{output: string, result: array<int|string, mixed>}
     */
    private function runWithCapture(ArrayIterator $dataFrame): array
    {
        $stream = fopen('php://memory', 'w+');
        $preview = new Preview($stream);

        $result = $this->iteratorToArray($preview($dataFrame));

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return ['output' => $output, 'result' => $result];
    }

    #[Test]
    public function dataframeItemsProducePrintedOutput(): void
    {
        $dataFrame = new ArrayIterator(['Alice', 'Bob']);
        $captured = $this->runWithCapture($dataFrame);

        $this->assertStringContainsString('Alice', $captured['output']);
        $this->assertStringContainsString('Bob', $captured['output']);
        $this->assertStringContainsString('Key:', $captured['output']);
        $this->assertStringContainsString('Value:', $captured['output']);
    }

    #[Test]
    public function nestedIteratorRowsArePrintedIndividually(): void
    {
        $nested = new ArrayIterator(['row1' => 'value1', 'row2' => 'value2']);
        $dataFrame = new ArrayIterator([$nested]);
        $captured = $this->runWithCapture($dataFrame);

        $this->assertStringContainsString('value1', $captured['output']);
        $this->assertStringContainsString('value2', $captured['output']);
        $this->assertStringContainsString('row1', $captured['output']);
        $this->assertStringContainsString('row2', $captured['output']);
    }

    #[Test]
    public function signalNextIsReturnedForEachItem(): void
    {
        $dataFrame = new ArrayIterator(['Alice', 'Bob', 'Charlie']);
        $captured = $this->runWithCapture($dataFrame);

        // Signal::Next means items are skipped (not yielded) in CallableDataFrame
        // Preview returns Signal::Next for every item, so no items are yielded
        $this->assertEmpty($captured['result']);
    }
}
