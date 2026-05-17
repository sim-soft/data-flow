<?php

namespace Simsoft\DataFlow\Tests\Extractors;

use ArrayIterator;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Extractors\IterableExtractor;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * IterableExtractorTest class
 *
 * Tests for the IterableExtractor class.
 */
#[CoversClass(IterableExtractor::class)]
class IterableExtractorTest extends TestCase
{
    #[Test]
    public function arrayIsConvertedToArrayIterator(): void
    {
        $extractor = new IterableExtractor([1, 2, 3]);
        $result = $extractor();

        $this->assertInstanceOf(ArrayIterator::class, $result);
    }

    #[Test]
    public function traversableIsAcceptedWithoutConversion(): void
    {
        $iterator = new ArrayIterator(['a', 'b', 'c']);
        $extractor = new IterableExtractor($iterator);
        $result = $extractor();

        $this->assertSame($iterator, $result);
    }

    #[Test]
    public function nonIterableThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid iterable provided');

        new IterableExtractor('not iterable');
    }

    #[Test]
    public function invocationYieldsAllItems(): void
    {
        $data = ['foo', 'bar', 'baz'];
        $extractor = new IterableExtractor($data);
        $result = $this->iteratorToArray($extractor());

        $this->assertSame($data, $result);
    }

    #[Test]
    public function emptyArrayYieldsNoItems(): void
    {
        $extractor = new IterableExtractor([]);
        $result = $this->iteratorToArray($extractor());

        $this->assertSame([], $result);
    }
}
