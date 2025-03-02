<?php

namespace Simsoft\DataFlow\Extractors;

use Iterator;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Simsoft\DataFlow\Extractor;

/**
 * FileFinderExtractor class.
 *
 * Extract file/ directory path for a given directory.
 */
class FileFinderExtractor extends Extractor
{
    const PATH_TYPE_ALL = 1;
    const PATH_TYPE_FILE = 2;
    const PATH_TYPE_DIRECTORY = 3;

    /** @var Filesystem Filesystem object. */
    protected Filesystem $filesystem;

    /** @var bool Whether to search recursively. */
    protected bool $recursive = false;

    /** @var int Return path type. */
    protected int $pathType = self::PATH_TYPE_ALL;

    /**
     * Constructor.
     *
     * @param string $directoryPath Directory path.
     * @param string $location
     */
    public function __construct(protected string $directoryPath = DIRECTORY_SEPARATOR, protected string $location = '/')
    {
        if (!str_ends_with($this->directoryPath, DIRECTORY_SEPARATOR)) {
            $this->directoryPath .= DIRECTORY_SEPARATOR;
        }

        $this->filesystem = new Filesystem(new LocalFilesystemAdapter($location));
    }

    /**
     * Get recursive.
     *
     * @return $this
     */
    public function recursive(): static
    {
        $this->recursive = true;
        return $this;
    }

    /**
     * Get directories only.
     *
     * @return $this
     */
    public function directoryOnly(): static
    {
        $this->pathType = static::PATH_TYPE_DIRECTORY;
        return $this;
    }

    /**
     * Get file only.
     *
     * @return $this
     */
    public function fileOnly(): static
    {
        $this->pathType = static::PATH_TYPE_FILE;
        return $this;
    }

    /**
     * @inheritDoc
     * @throws FilesystemException
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        $listing = $this->filesystem->listContents($this->directoryPath, $this->recursive);

        yield from match ($this->pathType) {
            static::PATH_TYPE_DIRECTORY => $listing->filter(fn($item) => $item->isDir()),
            static::PATH_TYPE_FILE => $listing->filter(fn($item) => !$item->isDir()),
            default => $listing,
        };
    }
}
