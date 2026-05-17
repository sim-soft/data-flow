<?php

namespace Simsoft\DataFlow\Tests\Transformers;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Transformers\Filter;

/**
 * FilterTest class
 *
 * Tests for the Filter transformer class.
 */
#[CoversClass(Filter::class)]
class FilterTest extends TestCase
{
    #[Test]
    public function allTruePredicateYieldsAllItems(): void
    {
        $filter = new Filter(fn($value) => true);
        $input = $this->arrayToIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray($filter($input));

        $this->assertSame([1, 2, 3, 4, 5], $result);
    }

    #[Test]
    public function allFalsePredicateYieldsNoItems(): void
    {
        $filter = new Filter(fn($value) => false);
        $input = $this->arrayToIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray($filter($input));

        $this->assertSame([], $result);
    }

    #[Test]
    public function selectivePredicateYieldsOnlyMatchingItems(): void
    {
        $filter = new Filter(fn($value) => $value > 2);
        $input = $this->arrayToIterator([1, 2, 3, 4, 5]);

        $result = $this->iteratorToArray($filter($input));

        $this->assertSame([2 => 3, 3 => 4, 4 => 5], $result);
    }

    #[Test]
    public function nullDataframeYieldsNoItems(): void
    {
        $filter = new Filter(fn($value) => true);

        $result = $this->iteratorToArray($filter(null));

        $this->assertSame([], $result);
    }

    #[Test]
    public function preservesOriginalKeysFromDataframe(): void
    {
        $filter = new Filter(fn($value) => $value % 2 === 0);
        $input = $this->arrayToIterator([10 => 'a', 20 => 'b', 30 => 'c', 40 => 'd']);

        // Predicate uses value, but let's filter by index to clearly show key preservation
        $filterByKey = new Filter(fn($value, $index) => $index >= 20);
        $result = $this->iteratorToArray($filterByKey($input));

        $this->assertSame([20 => 'b', 30 => 'c', 40 => 'd'], $result);
    }
}
