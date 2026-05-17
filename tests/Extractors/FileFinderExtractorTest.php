<?php

namespace Simsoft\DataFlow\Tests\Extractors;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Simsoft\DataFlow\Extractors\FileFinderExtractor;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * FileFinderExtractorTest class
 *
 * Tests for the FileFinderExtractor class.
 */
#[CoversClass(FileFinderExtractor::class)]
class FileFinderExtractorTest extends TestCase
{
    /**
     * Inject a mock Filesystem into the FileFinderExtractor via reflection.
     *
     * @param FileFinderExtractor $extractor The extractor instance.
     * @param Filesystem $filesystem The mock filesystem.
     * @return void
     */
    private function injectFilesystem(FileFinderExtractor $extractor, Filesystem $filesystem): void
    {
        $reflection = new ReflectionClass($extractor);
        $property = $reflection->getProperty('filesystem');
        $property->setValue($extractor, $filesystem);
    }

    /**
     * Create a mock Filesystem that returns the given items from listContents.
     *
     * @param array $items Array of StorageAttributes items.
     * @param string $expectedPath Expected directory path argument.
     * @param bool $expectedDeep Expected recursive flag.
     * @return Filesystem
     */
    private function createMockFilesystem(array $items, string $expectedPath = '/', bool $expectedDeep = false): Filesystem
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('listContents')
            ->with($expectedPath, $expectedDeep)
            ->willReturn(new DirectoryListing($items));

        return $filesystem;
    }

    #[Test]
    public function pathIsNormalizedWithTrailingSeparator(): void
    {
        $extractor = new FileFinderExtractor('/some/path');

        $reflection = new ReflectionClass($extractor);
        $property = $reflection->getProperty('directoryPath');
        $directoryPath = $property->getValue($extractor);

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, $directoryPath);
    }

    #[Test]
    public function pathAlreadyWithTrailingSeparatorIsNotDoubled(): void
    {
        $extractor = new FileFinderExtractor('/some/path' . DIRECTORY_SEPARATOR);

        $reflection = new ReflectionClass($extractor);
        $property = $reflection->getProperty('directoryPath');
        $directoryPath = $property->getValue($extractor);

        // Should not have double separator at end
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR, $directoryPath);
        $this->assertStringEndsNotWith(
            DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            $directoryPath
        );
    }

    #[Test]
    public function recursiveTraversesSubdirectories(): void
    {
        $items = [
            new FileAttributes('dir/file1.txt'),
            new DirectoryAttributes('dir/subdir'),
            new FileAttributes('dir/subdir/file2.txt'),
        ];

        $extractor = new FileFinderExtractor('dir' . DIRECTORY_SEPARATOR);
        $extractor->recursive();

        $filesystem = $this->createMockFilesystem($items, 'dir' . DIRECTORY_SEPARATOR, true);
        $this->injectFilesystem($extractor, $filesystem);

        $result = iterator_to_array($extractor(), false);

        $this->assertCount(3, $result);
    }

    #[Test]
    public function fileOnlyYieldsOnlyFiles(): void
    {
        $items = [
            new FileAttributes('dir/file1.txt'),
            new DirectoryAttributes('dir/subdir'),
            new FileAttributes('dir/file2.txt'),
        ];

        $extractor = new FileFinderExtractor('dir' . DIRECTORY_SEPARATOR);
        $extractor->fileOnly();

        $filesystem = $this->createMockFilesystem($items, 'dir' . DIRECTORY_SEPARATOR, false);
        $this->injectFilesystem($extractor, $filesystem);

        $result = iterator_to_array($extractor(), false);

        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertInstanceOf(FileAttributes::class, $item);
        }
    }

    #[Test]
    public function directoryOnlyYieldsOnlyDirectories(): void
    {
        $items = [
            new FileAttributes('dir/file1.txt'),
            new DirectoryAttributes('dir/subdir1'),
            new DirectoryAttributes('dir/subdir2'),
        ];

        $extractor = new FileFinderExtractor('dir' . DIRECTORY_SEPARATOR);
        $extractor->directoryOnly();

        $filesystem = $this->createMockFilesystem($items, 'dir' . DIRECTORY_SEPARATOR, false);
        $this->injectFilesystem($extractor, $filesystem);

        $result = iterator_to_array($extractor(), false);

        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertInstanceOf(DirectoryAttributes::class, $item);
        }
    }

    #[Test]
    public function defaultYieldsBothFilesAndDirectories(): void
    {
        $items = [
            new FileAttributes('dir/file1.txt'),
            new DirectoryAttributes('dir/subdir'),
            new FileAttributes('dir/file2.txt'),
        ];

        $extractor = new FileFinderExtractor('dir' . DIRECTORY_SEPARATOR);

        $filesystem = $this->createMockFilesystem($items, 'dir' . DIRECTORY_SEPARATOR, false);
        $this->injectFilesystem($extractor, $filesystem);

        $result = iterator_to_array($extractor(), false);

        $this->assertCount(3, $result);

        $files = array_filter($result, fn($item) => $item instanceof FileAttributes);
        $dirs = array_filter($result, fn($item) => $item instanceof DirectoryAttributes);

        $this->assertCount(2, $files);
        $this->assertCount(1, $dirs);
    }

    #[Test]
    public function recursiveReturnsSelf(): void
    {
        $extractor = new FileFinderExtractor('/some/path' . DIRECTORY_SEPARATOR);
        $result = $extractor->recursive();

        $this->assertSame($extractor, $result);
    }

    #[Test]
    public function fileOnlyReturnsSelf(): void
    {
        $extractor = new FileFinderExtractor('/some/path' . DIRECTORY_SEPARATOR);
        $result = $extractor->fileOnly();

        $this->assertSame($extractor, $result);
    }

    #[Test]
    public function directoryOnlyReturnsSelf(): void
    {
        $extractor = new FileFinderExtractor('/some/path' . DIRECTORY_SEPARATOR);
        $result = $extractor->directoryOnly();

        $this->assertSame($extractor, $result);
    }
}
