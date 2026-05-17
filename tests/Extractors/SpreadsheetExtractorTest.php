<?php

namespace Simsoft\DataFlow\Tests\Extractors;

use ArrayIterator;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Extractors\SpreadsheetExtractor;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * SpreadsheetExtractorTest class
 *
 * Tests for the SpreadsheetExtractor class.
 */
#[CoversClass(SpreadsheetExtractor::class)]
class SpreadsheetExtractorTest extends TestCase
{
    #[Test]
    public function validFilePathLoadsSpreadsheet(): void
    {
        $extractor = SpreadsheetExtractor::from($this->fixturePath('sample.xlsx'));
        $result = $this->iteratorToArray($extractor());

        $this->assertNotEmpty($result);
        $this->assertCount(3, $result);
    }

    #[Test]
    public function nonExistentFileThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('File not found:');

        SpreadsheetExtractor::from('/non/existent/file.xlsx');
    }

    #[Test]
    public function customHeadersAreUsedAsKeys(): void
    {
        $extractor = SpreadsheetExtractor::from($this->fixturePath('sample.xlsx'))
            ->headers(['col_id', 'col_name', 'col_email']);

        $result = $this->iteratorToArray($extractor());

        $firstRow = reset($result);
        $this->assertArrayHasKey('col_id', $firstRow);
        $this->assertArrayHasKey('col_name', $firstRow);
        $this->assertArrayHasKey('col_email', $firstRow);
    }

    #[Test]
    public function defaultFirstRowIsUsedAsHeaders(): void
    {
        $extractor = SpreadsheetExtractor::from($this->fixturePath('sample.xlsx'));
        $result = $this->iteratorToArray($extractor());

        $firstRow = reset($result);
        $this->assertArrayHasKey('id', $firstRow);
        $this->assertArrayHasKey('name', $firstRow);
        $this->assertArrayHasKey('email', $firstRow);
    }

    #[Test]
    public function sheetSelectionReadsSpecifiedSheet(): void
    {
        $extractor = SpreadsheetExtractor::from($this->fixturePath('sample.xlsx'))
            ->sheet('Address');

        $result = $this->iteratorToArray($extractor());

        $this->assertCount(3, $result);
        $firstRow = reset($result);
        $this->assertArrayHasKey('id', $firstRow);
        $this->assertArrayHasKey('city', $firstRow);
        $this->assertArrayHasKey('country', $firstRow);
    }

    #[Test]
    public function nullFilePathReadsFromDataFrame(): void
    {
        $extractor = new SpreadsheetExtractor(null);
        $dataFrame = new ArrayIterator([$this->fixturePath('sample.xlsx')]);

        $result = $this->iteratorToArray($extractor($dataFrame));

        $this->assertCount(3, $result);
        $firstRow = reset($result);
        $this->assertArrayHasKey('id', $firstRow);
        $this->assertArrayHasKey('name', $firstRow);
        $this->assertArrayHasKey('email', $firstRow);
    }
}
