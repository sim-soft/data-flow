<?php

namespace Simsoft\DataFlow\Extractors;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Writer\Exception\InvalidSheetNameException;
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
    protected SpoutIO $spreadsheet;

    /** @var bool Indicate that the current file contains headers. */
    protected bool $withHeaders = true;

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
            $this->spreadsheet = SpoutIO::readFromFile($filepath, csvDelimiter: $csvDelimiter);

        } catch (IOException|UnsupportedTypeException $throwable) {
            error_log($throwable->getMessage());
        }
    }

    /**
     * Set no headers.
     *
     * @return $this
     */
    public function withoutHeaders(): static
    {
        $this->withHeaders = false;
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
        return $this->spreadsheet->getReader();
    }

    /**
     * @inheritDoc
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        try {
            $this->withHeaders && $this->spreadsheet->withHeaders();

            if ($this->sheetNameOrIndex) {
                yield from $this->spreadsheet->getSheetRows($this->sheetNameOrIndex);
            } else {
                foreach ($this->getReader()->getSheetIterator() as $sheet) {
                    yield from $this->spreadsheet->getSheetRows($sheet->getName());
                }
            }

            $this->getReader()->close();

        } catch (ReaderNotOpenedException|SheetNotFoundException|WriterNotOpenedException|InvalidSheetNameException $throwable) {
            error_log($throwable->getMessage());
        }
    }
}
