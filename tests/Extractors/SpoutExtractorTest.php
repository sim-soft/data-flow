<?php

namespace Simsoft\DataFlow\Tests\Extractors;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\ExtractorException;
use Simsoft\DataFlow\Extractors\SpoutExtractor;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * SpoutExtractorTest class
 *
 * Tests for the SpoutExtractor class.
 */
#[CoversClass(SpoutExtractor::class)]
class SpoutExtractorTest extends TestCase
{
    #[Test]
    public function validXlsxFileConstructsWithoutError(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.xlsx'));

        $this->assertInstanceOf(SpoutExtractor::class, $extractor);
    }

    #[Test]
    public function validCsvFileConstructsWithoutError(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.csv'));

        $this->assertInstanceOf(SpoutExtractor::class, $extractor);
    }

    #[Test]
    public function invalidFilePathThrowsExtractorException(): void
    {
        $this->expectException(ExtractorException::class);
        $this->expectExceptionMessage('Failed to open file for reading');

        new SpoutExtractor('/nonexistent/path/file.xlsx');
    }

    #[Test]
    public function withoutHeadersReadsAllRowsAsIndexedArrays(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.csv'));
        $extractor->withoutHeaders();

        $rows = [];
        foreach ($extractor() as $row) {
            $rows[] = $row;
        }

        // Without headers, the first row (header row) is included as data
        $this->assertCount(6, $rows);
        // First row should be the header values as indexed array
        $this->assertSame(['id', 'name', 'email', 'age', 'city'], $rows[0]);
    }

    #[Test]
    public function withHeadersReadsRowsAsAssociativeArrays(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.csv'));

        $rows = [];
        foreach ($extractor() as $row) {
            $rows[] = $row;
        }

        // With headers (default), the first row is used as keys
        $this->assertCount(5, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('email', $rows[0]);
        $this->assertSame('Alice', $rows[0]['name']);
    }

    #[Test]
    public function sheetWithNameReadsOnlySpecifiedSheet(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.xlsx'));
        $extractor->sheet('Profile');

        $rows = [];
        foreach ($extractor() as $key => $row) {
            $rows[] = $row;
        }

        // Profile sheet has 3 data rows (header is consumed)
        $this->assertCount(3, $rows);
        $this->assertArrayHasKey('id', $rows[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertArrayHasKey('email', $rows[0]);
        $this->assertSame('Alice', $rows[0]['name']);
        $this->assertSame('Bob', $rows[1]['name']);
        $this->assertSame('Charlie', $rows[2]['name']);
    }

    #[Test]
    public function multiSheetSequentialReadingWithoutSheetSpecified(): void
    {
        $extractor = new SpoutExtractor($this->fixturePath('sample.xlsx'));

        $rows = [];
        $keys = [];
        foreach ($extractor() as $key => $row) {
            $rows[] = $row;
            $keys[] = $key;
        }

        // Both sheets should be read: Profile (3 rows) + Address (3 rows) = 6 rows
        $this->assertCount(6, $rows);

        // First 3 rows from Profile sheet
        $this->assertSame('Profile', $keys[0]);
        $this->assertArrayHasKey('name', $rows[0]);
        $this->assertSame('Alice', $rows[0]['name']);

        // Last 3 rows from Address sheet
        $this->assertSame('Address', $keys[3]);
        $this->assertArrayHasKey('city', $rows[3]);
        $this->assertSame('New York', $rows[3]['city']);
    }
}
