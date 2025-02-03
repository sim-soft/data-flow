<?php

namespace Simsoft\DataFlow\Extractors;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Writer\Exception\SheetNotFoundException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Iterator;
use Simsoft\DataFlow\Extractor;
use Simsoft\Spreadsheet\SpoutIO;

/**
 * SpoutExtractor class.
 */
class SpoutExtractor extends Extractor
{
    /** @var SpoutIO The reader object. */
    protected SpoutIO $reader;

    /** @var bool Indicate that the current file contains headers. */
    protected bool $headerExists = false;

    /** @var string|int|null Sheet name or index */
    protected string|int|null $sheetNameOrIndex = null;

    /**
     * Constructor.
     *
     * @param string $filepath
     * @param string $csvDelimiter CSV delimiter. If the current file is CSV.
     */
    public function __construct(protected string $filepath, string $csvDelimiter = ',')
    {
        try {
            $this->reader = SpoutIO::readFromFile($filepath, csvDelimiter: $csvDelimiter);

        } catch (IOException|UnsupportedTypeException $throwable) {
            var_dump($throwable->getMessage());
        }
    }

    /**
     * Indicate that the current file contains headers.
     *
     * @return $this
     */
    public function withHeaders(): static
    {
        $this->headerExists = true;
        return $this;
    }

    /**
     * Set sheet name or index
     *
     * @param string|int $sheetNameOrIndex
     * @return $this
     */
    public function sheet(string|int $sheetNameOrIndex): static
    {
        $this->sheetNameOrIndex = $sheetNameOrIndex;
        return $this;
    }

    /**
     * Get reader.
     *
     * @return ReaderInterface
     */
    public function &getReader(): ReaderInterface
    {
        return $this->reader->getReader();
    }

    /**
     * @inheritDoc
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        try {
            if ($this->headerExists) {
                $this->reader->withHeaders();
            }

            yield from $this->reader->getSheetRows($this->sheetNameOrIndex);
        } catch (ReaderNotOpenedException|SheetNotFoundException|WriterNotOpenedException $throwable) {

        }
    }
}
