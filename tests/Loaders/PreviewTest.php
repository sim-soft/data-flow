<?php

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loaders\Preview;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * PreviewTest class.
 */
#[CoversClass(Preview::class)]
class PreviewTest extends TestCase
{
    #[Test]
    public function dataframeItemsProducePrintedOutput(): void
    {
        $preview = new Preview();
        $dataFrame = new ArrayIterator(['Alice', 'Bob']);

        ob_start();
        $result = $this->iteratorToArray($preview($dataFrame));
        $output = ob_get_clean();

        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('Bob', $output);
        $this->assertStringContainsString('Key:', $output);
        $this->assertStringContainsString('Value:', $output);
    }

    #[Test]
    public function nestedIteratorRowsArePrintedIndividually(): void
    {
        $preview = new Preview();
        $nested = new ArrayIterator(['row1' => 'value1', 'row2' => 'value2']);
        $dataFrame = new ArrayIterator([$nested]);

        ob_start();
        $result = $this->iteratorToArray($preview($dataFrame));
        $output = ob_get_clean();

        $this->assertStringContainsString('value1', $output);
        $this->assertStringContainsString('value2', $output);
        $this->assertStringContainsString('row1', $output);
        $this->assertStringContainsString('row2', $output);
    }

    #[Test]
    public function signalNextIsReturnedForEachItem(): void
    {
        $preview = new Preview();
        $dataFrame = new ArrayIterator(['Alice', 'Bob', 'Charlie']);

        ob_start();
        $result = $this->iteratorToArray($preview($dataFrame));
        ob_get_clean();

        // Signal::Next means items are skipped (not yielded) in CallableDataFrame
        // Preview returns Signal::Next for every item, so no items are yielded
        $this->assertEmpty($result);
    }
}
