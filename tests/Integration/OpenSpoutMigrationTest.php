<?php

namespace Simsoft\DataFlow\Tests\Integration;

use ArrayIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Extractors\SpoutExtractor;
use Simsoft\DataFlow\Loaders\SpoutLoader;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * OpenSpoutMigrationTest class.
 *
 * Integration tests verifying the OpenSpout migration is complete:
 * - SpoutExtractor reads XLSX without deprecation warnings
 * - SpoutLoader writes XLSX without deprecation warnings
 * - Vendored Box\Spout directory removed
 * - composer.json has no Box\Spout autoload entries
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversClass(SpoutExtractor::class)]
#[CoversClass(SpoutLoader::class)]
class OpenSpoutMigrationTest extends TestCase
{
    /** @var string Temp directory for test files. */
    private string $tempDir;

    /** @var string[] Glob patterns for cleanup. */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

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

    private function registerTempFile(string $baseName): void
    {
        $this->tempFiles[] = $this->tempDir . DIRECTORY_SEPARATOR . $baseName . '*';
    }

    #[Test]
    public function spoutLoaderWritesXlsxWithoutDeprecationWarnings(): void
    {
        $this->registerTempFile('openspout_write_test');

        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'openspout_write_test.xlsx';
        $loader = new SpoutLoader($filepath);

        $dataFrame = new ArrayIterator([
            'Sheet1' => ['name' => 'Alice', 'age' => 30],
            'Sheet1' => ['name' => 'Bob', 'age' => 25],
        ]);

        // If any deprecation warnings are triggered, PHPUnit will report them
        // since composer test runs with --display-deprecations
        iterator_to_array($loader($dataFrame));

        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'openspout_write_test*.xlsx');
        $this->assertNotEmpty($files, 'SpoutLoader should create an XLSX file');
        $this->assertFileExists($files[0]);
    }

    #[Test]
    public function spoutExtractorReadsXlsxWithoutDeprecationWarnings(): void
    {
        $this->registerTempFile('openspout_read_test');

        // First, write a file using SpoutLoader
        $filepath = $this->tempDir . DIRECTORY_SEPARATOR . 'openspout_read_test.xlsx';
        $loader = new SpoutLoader($filepath);

        $dataFrame = new ArrayIterator([
            'Sheet1' => ['name' => 'Alice', 'email' => 'alice@example.com'],
            'Sheet1' => ['name' => 'Bob', 'email' => 'bob@example.com'],
        ]);
        iterator_to_array($loader($dataFrame));

        // Find the created file (timestamped)
        $files = glob($this->tempDir . DIRECTORY_SEPARATOR . 'openspout_read_test*.xlsx');
        $this->assertNotEmpty($files, 'Expected XLSX file to be created for reading test');

        // Now read it back with SpoutExtractor - no deprecation warnings should occur
        $extractor = new SpoutExtractor($files[0]);
        $rows = iterator_to_array($extractor());

        // Verify data was read successfully
        $this->assertNotEmpty($rows, 'SpoutExtractor should read rows from the XLSX file');
    }

    #[Test]
    public function vendoredBoxSpoutDirectoryDoesNotExist(): void
    {
        $boxSpoutPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'simsoft'
            . DIRECTORY_SEPARATOR . 'box' . DIRECTORY_SEPARATOR . 'spout';

        $this->assertDirectoryDoesNotExist(
            $boxSpoutPath,
            'The vendored simsoft/box/spout directory should not exist after OpenSpout migration'
        );
    }

    #[Test]
    public function composerJsonHasNoBoxSpoutAutoloadEntries(): void
    {
        $composerJsonPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';
        $this->assertFileExists($composerJsonPath);

        $composerContent = file_get_contents($composerJsonPath);
        $this->assertIsString($composerContent);

        $composer = json_decode($composerContent, true);
        $this->assertIsArray($composer);

        // Check PSR-4 autoload for Box\Spout references
        if (isset($composer['autoload']['psr-4'])) {
            foreach ($composer['autoload']['psr-4'] as $namespace => $path) {
                $this->assertStringNotContainsString(
                    'Box\\Spout',
                    $namespace,
                    "composer.json autoload.psr-4 should not contain Box\\Spout namespace: found '$namespace'"
                );
                $this->assertStringNotContainsString(
                    'box/spout',
                    is_array($path) ? implode(',', $path) : $path,
                    "composer.json autoload.psr-4 should not reference box/spout path"
                );
            }
        }

        // Check classmap autoload for Box\Spout references
        if (isset($composer['autoload']['classmap'])) {
            foreach ($composer['autoload']['classmap'] as $entry) {
                $this->assertStringNotContainsString(
                    'box/spout',
                    $entry,
                    "composer.json autoload.classmap should not reference box/spout: found '$entry'"
                );
            }
        }

        // Check that the raw JSON content has no Box\Spout references at all
        $this->assertStringNotContainsString(
            'Box\\\\Spout',
            $composerContent,
            'composer.json should not contain any Box\\Spout namespace references'
        );

        $this->assertStringNotContainsString(
            'box/spout',
            $composerContent,
            'composer.json should not contain any box/spout path references'
        );
    }
}
