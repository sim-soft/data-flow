<?php

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Loaders\SpreadsheetLoader;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * SpreadsheetLoaderTest class.
 *
 * Tests for the SpreadsheetLoader class.
 */
#[CoversClass(SpreadsheetLoader::class)]
class SpreadsheetLoaderTest extends TestCase
{
    /** @var string[] Files to clean up after each test. */
    private array $tempFiles = [];

    /** @var string Temp directory for output files (no dots in path). */
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        // Use a path without dots in directory names to avoid SpreadsheetLoader's
        // explode('.') path parsing splitting on directory dots.
        $this->tempDir = 'C:\\temp\\dftest';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        // Also clean up any glob-matched files with timestamps
        foreach (glob($this->tempDir . '/spreadsheet_loader_test_*') as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    /**
     * Register a temp file for cleanup.
     */
    private function trackTempFile(string $path): void
    {
        $this->tempFiles[] = $path;
    }

    #[Test]
    public function constructorParsesPathAndExtension(): void
    {
        $loader = new SpreadsheetLoader('/tmp/output.xlsx');

        // Use reflection to verify internal state
        $reflection = new \ReflectionClass($loader);

        $filePathProp = $reflection->getProperty('filePath');
        $filePathProp->setAccessible(true);

        $extensionProp = $reflection->getProperty('extension');
        $extensionProp->setAccessible(true);

        $this->assertSame('/tmp/output', $filePathProp->getValue($loader));
        $this->assertSame('xlsx', $extensionProp->getValue($loader));
    }

    #[Test]
    public function constructorParsesPathWithCsvExtension(): void
    {
        $loader = new SpreadsheetLoader('/tmp/report.csv');

        $reflection = new \ReflectionClass($loader);

        $filePathProp = $reflection->getProperty('filePath');
        $filePathProp->setAccessible(true);

        $extensionProp = $reflection->getProperty('extension');
        $extensionProp->setAccessible(true);

        $this->assertSame('/tmp/report', $filePathProp->getValue($loader));
        $this->assertSame('csv', $extensionProp->getValue($loader));
    }

    #[Test]
    public function constructorWithoutExtensionUsesDefaultXlsx(): void
    {
        $loader = new SpreadsheetLoader('/tmp/output');

        $reflection = new \ReflectionClass($loader);

        $extensionProp = $reflection->getProperty('extension');
        $extensionProp->setAccessible(true);

        $filePathProp = $reflection->getProperty('filePath');
        $filePathProp->setAccessible(true);

        $this->assertSame('xlsx', $extensionProp->getValue($loader));
        $this->assertSame('/tmp/output', $filePathProp->getValue($loader));
    }

    #[Test]
    public function appendDisablesTimestampInFilename(): void
    {
        $basePath = $this->tempDir . '/spreadsheet_loader_test_append';
        $expectedFile = $basePath . '.xlsx';
        $this->trackTempFile($expectedFile);

        $loader = (new SpreadsheetLoader($basePath . '.xlsx'))->append();

        $dataFrame = new ArrayIterator([
            ['name' => 'Alice', 'age' => 30],
        ]);

        $result = $loader($dataFrame);
        // Consume the iterator to trigger writing
        iterator_to_array($result);

        $this->assertFileExists($expectedFile);
    }

    #[Test]
    public function withoutAppendAddsTimestampToFilename(): void
    {
        $basePath = $this->tempDir . '/spreadsheet_loader_test_timestamp';

        $loader = new SpreadsheetLoader($basePath . '.xlsx');

        $dataFrame = new ArrayIterator([
            ['name' => 'Bob', 'age' => 25],
        ]);

        $result = $loader($dataFrame);
        iterator_to_array($result);

        // The file should have a timestamp appended (not the plain base path)
        $this->assertFileDoesNotExist($basePath . '.xlsx');

        // Find the timestamped file
        $files = glob($basePath . '_*.xlsx');
        $this->assertNotEmpty($files, 'Expected a timestamped file to be created');

        // Track for cleanup
        foreach ($files as $file) {
            $this->trackTempFile($file);
        }
    }

    #[Test]
    public function sheetSetsSheetName(): void
    {
        $basePath = $this->tempDir . '/spreadsheet_loader_test_sheet';
        $expectedFile = $basePath . '.xlsx';
        $this->trackTempFile($expectedFile);

        $loader = (new SpreadsheetLoader($basePath . '.xlsx'))
            ->append()
            ->sheet('CustomSheet');

        $dataFrame = new ArrayIterator([
            ['id' => 1, 'value' => 'test'],
        ]);

        $result = $loader($dataFrame);
        iterator_to_array($result);

        $this->assertFileExists($expectedFile);

        // Verify the sheet name by reading the file back
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($expectedFile);
        $sheetNames = $spreadsheet->getSheetNames();
        $this->assertContains('CustomSheet', $sheetNames);
    }

    #[Test]
    public function arrayDataRowsAreWrittenToSpreadsheet(): void
    {
        $basePath = $this->tempDir . '/spreadsheet_loader_test_array';
        $expectedFile = $basePath . '.xlsx';
        $this->trackTempFile($expectedFile);

        $loader = (new SpreadsheetLoader($basePath . '.xlsx'))->append();

        $dataFrame = new ArrayIterator([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ]);

        $result = $loader($dataFrame);
        iterator_to_array($result);

        $this->assertFileExists($expectedFile);

        // Read back and verify content
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($expectedFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1 should be headers (auto-detected from associative array keys)
        $this->assertSame('name', $sheet->getCell('A1')->getValue());
        $this->assertSame('age', $sheet->getCell('B1')->getValue());

        // Row 2 should be first data row
        $this->assertSame('Alice', $sheet->getCell('A2')->getValue());
        $this->assertSame(30, $sheet->getCell('B2')->getValue());

        // Row 3 should be second data row
        $this->assertSame('Bob', $sheet->getCell('A3')->getValue());
        $this->assertSame(25, $sheet->getCell('B3')->getValue());

        // Row 4 should be third data row
        $this->assertSame('Charlie', $sheet->getCell('A4')->getValue());
        $this->assertSame(35, $sheet->getCell('B4')->getValue());
    }

    #[Test]
    public function iteratorDataIsWrittenInBatches(): void
    {
        $basePath = $this->tempDir . '/spreadsheet_loader_test_iterator';
        $expectedFile = $basePath . '.xlsx';
        $this->trackTempFile($expectedFile);

        $loader = (new SpreadsheetLoader($basePath . '.xlsx'))->append();

        // Create an inner iterator with more than 10 rows to trigger batch writing
        $innerRows = [];
        for ($i = 1; $i <= 15; $i++) {
            $innerRows[] = ['id' => $i, 'value' => "item_$i"];
        }
        $innerIterator = new ArrayIterator($innerRows);

        // The dataframe yields an Iterator item (triggers the Iterator branch in __invoke)
        $dataFrame = new ArrayIterator([$innerIterator]);

        $result = $loader($dataFrame);
        iterator_to_array($result);

        $this->assertFileExists($expectedFile);

        // Read back and verify all 15 rows were written
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($expectedFile);
        $sheet = $spreadsheet->getActiveSheet();

        // Row 1 is headers, rows 2-16 are data
        $highestRow = $sheet->getHighestRow();
        $this->assertSame(16, $highestRow); // 1 header + 15 data rows

        // Verify first and last data rows
        $this->assertSame(1, $sheet->getCell('A2')->getValue());
        $this->assertSame('item_1', $sheet->getCell('B2')->getValue());
        $this->assertSame(15, $sheet->getCell('A16')->getValue());
        $this->assertSame('item_15', $sheet->getCell('B16')->getValue());
    }
}
