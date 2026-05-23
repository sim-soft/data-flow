<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Tests\Extractors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Extractors\ActiveQueryExtractor;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DB\Builder\ActiveQuery;

/**
 * ActiveQueryExtractorTest class
 *
 * Requires simsoft/fliq installed as dev dependency.
 */
#[CoversClass(ActiveQueryExtractor::class)]
class ActiveQueryExtractorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists(ActiveQuery::class)) {
            $this->markTestSkipped('simsoft/fliq is not installed.');
        }
    }

    #[Test]
    public function constructsWithActiveQuery(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);

        $this->assertInstanceOf(ActiveQueryExtractor::class, $extractor);
    }

    #[Test]
    public function sizeMethodReturnsSelf(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);

        $this->assertSame($extractor, $extractor->size(50));
    }

    #[Test]
    public function toArrayMethodReturnsSelf(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);

        $this->assertSame($extractor, $extractor->toArray());
    }

    #[Test]
    public function debugMethodReturnsSelf(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);

        $this->assertSame($extractor, $extractor->debug());
    }

    #[Test]
    public function makeFactoryReturnsInstance(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = ActiveQueryExtractor::make($query, 25);

        $this->assertInstanceOf(ActiveQueryExtractor::class, $extractor);
    }

    #[Test]
    public function defaultSizeIsOneHundred(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);

        // Access via reflection
        $ref = new \ReflectionProperty($extractor, 'size');
        $this->assertSame(100, $ref->getValue($extractor));
    }

    #[Test]
    public function sizeIsConfigurable(): void
    {
        $query = $this->createStub(ActiveQuery::class);
        $extractor = new ActiveQueryExtractor($query);
        $extractor->size(50);

        $ref = new \ReflectionProperty($extractor, 'size');
        $this->assertSame(50, $ref->getValue($extractor));
    }
}
