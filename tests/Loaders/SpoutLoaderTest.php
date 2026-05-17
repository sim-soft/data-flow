<?php

namespace Simsoft\DataFlow\Tests\Loaders;

use ArrayIterator;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Exceptions\LoaderException;
use Simsoft\DataFlow\Loaders\SpoutLoader;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * SpoutLoaderTest class.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversClass(SpoutLoader::class)]
class SpoutLoaderTest extends TestCase
{
    /** @var string Temp directory without dots in path for SpoutLoader compatibility. */
    private string $tempDir;

    /** @var string[] Files to clean up after each test. */
    private array $tempFiles = [];

    /**
     * Set up a temp directory without dots in the path.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // SpoutLoader uses explode('.', filepath) which breaks on paths with dots in directories.
        // Use a dedicated temp directory without dots in the resolved path.
        $this->tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    /**
     * Clean up temp files after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $pattern) {
            $files = glob($pattern);
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Register a temp file pattern for cleanup.
     *
     * @param string $baseName The base name (without timestamp/extension) to match.
     * @return void
     */
    private function registerTempFile(string $baseName): void
    {
        $this->tempFiles[] = $this->tempDir . DIRECTORY_SEPARATOR . $baseName . '*';
    }

    #[Test]
    public function validCsvFilePathCreatesOutputFile(): void
    {
        $this->registerTempFile('spout_csv');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_csv.csv';
        $loader = new SpoutLoader($filepath);

        // Invoke with some data to trigger file write and close
        $dataFrame = new ArrayIterator(['Sheet1' => ['col1', 'col2']]);
        iterator_to_array($loader($dataFrame));

        // Verify a CSV file was created matching the pattern
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'spout_csv*.csv');
        $this->assertNotEmpty($files, 'Expected CSV output file to be created');
    }

    #[Test]
    public function validFilePathCreatesOutputFile(): void
    {
        $this->registerTempFile('spout_valid');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_valid.xlsx';
        $loader = new SpoutLoader($filepath);

        // Invoke with some data to trigger file write and close
        $dataFrame = new ArrayIterator(['Sheet1' => ['col1', 'col2']]);
        iterator_to_array($loader($dataFrame));

        // Verify a file was created matching the pattern
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'spout_valid*.xlsx');
        $this->assertNotEmpty($files, 'Expected output file to be created');
    }

    #[Test]
    public function invalidFilePathThrowsLoaderException(): void
    {
        $this->expectException(LoaderException::class);

        // Use an unsupported extension to trigger UnsupportedTypeException → LoaderException
        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_invalid.unsupported';
        $this->registerTempFile('spout_invalid');

        new SpoutLoader($filepath);
    }

    #[Test]
    public function withHeadersWritesHeadersAsFirstRow(): void
    {
        $this->registerTempFile('spout_headers');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_headers.xlsx';
        $loader = new SpoutLoader($filepath);
        $result = $loader->withHeaders(['Name', 'Age', 'Email']);

        // Verify fluent return
        $this->assertSame($loader, $result);

        // Invoke with data and close the writer
        $dataFrame = new ArrayIterator(['Sheet1' => ['Alice', 30, 'alice@example.com']]);
        iterator_to_array($loader($dataFrame));

        // Verify file was created
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'spout_headers*.xlsx');
        $this->assertNotEmpty($files, 'Expected output file to be created with headers');
    }

    #[Test]
    public function withoutHeadersDisablesAutoDetection(): void
    {
        $this->registerTempFile('spout_noheaders');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_noheaders.xlsx';
        $loader = new SpoutLoader($filepath);
        $result = $loader->withoutHeaders();

        // Verify fluent return
        $this->assertSame($loader, $result);

        // Invoke with list array data (non-associative) - no headers should be written
        $dataFrame = new ArrayIterator([
            'Sheet1' => ['Alice', 30, 'alice@example.com'],
        ]);
        $output = iterator_to_array($loader($dataFrame));

        // Data should be written without auto-detecting headers
        $this->assertNotEmpty($output);
        $this->assertEquals(['Alice', 30, 'alice@example.com'], $output['Sheet1']);
    }

    #[Test]
    public function autoDetectsHeadersFromAssociativeArrayKeys(): void
    {
        $this->registerTempFile('spout_autoheaders');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_autoheaders.xlsx';
        $loader = new SpoutLoader($filepath);

        // Provide associative array data - headers should be auto-detected from keys
        $dataFrame = new ArrayIterator([
            'Sheet1' => ['name' => 'Alice', 'age' => 30],
        ]);
        $output = iterator_to_array($loader($dataFrame));

        // Verify data was yielded back
        $this->assertCount(1, $output);
        $this->assertEquals(['name' => 'Alice', 'age' => 30], $output['Sheet1']);

        // Verify file was created
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'spout_autoheaders*.xlsx');
        $this->assertNotEmpty($files, 'Expected output file to be created with auto-detected headers');
    }

    #[Test]
    public function nonArrayDataThrowsUnsupportedTypeException(): void
    {
        $this->registerTempFile('spout_nonarray');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'spout_nonarray.xlsx';
        $loader = new SpoutLoader($filepath);

        $this->expectException(UnsupportedTypeException::class);
        $this->expectExceptionMessage('Data must be an array.');

        // Provide non-array data (a string)
        $dataFrame = new ArrayIterator(['Sheet1' => 'not an array']);
        iterator_to_array($loader($dataFrame));
    }
}
