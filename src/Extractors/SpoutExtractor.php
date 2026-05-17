<?php

namespace Simsoft\DataFlow\Extractors;

use Iterator;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\Exception\ReaderNotOpenedException;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Writer\Exception\InvalidSheetNameException;
use OpenSpout\Writer\Exception\SheetNotFoundException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use Simsoft\DataFlow\Exceptions\ExtractorException;
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
     * @throws ExtractorException
     */
    public function __construct(protected string $filepath, string $csvDelimiter = ',')
    {
        try {
            $this->spreadsheet = SpoutIO::readFromFile($filepath, csvDelimiter: $csvDelimiter);
        } catch (IOException|UnsupportedTypeException $throwable) {
            throw new ExtractorException(
                "Failed to open file for reading: {$filepath}",
                previous: $throwable
            );
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
     * @return ReaderInterface|null
     */
    /** @phpstan-ignore missingType.generics */
    public function &getReader(): ?ReaderInterface
    {
        return $this->spreadsheet->getReader();
    }

    /**
     * @inheritDoc
     * @throws ExtractorException
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        try {
            $this->withHeaders && $this->spreadsheet->withHeaders();

            if ($this->sheetNameOrIndex) {
                yield from $this->spreadsheet->getSheetRows($this->sheetNameOrIndex);
            } elseif ($this->getReader()) {
                foreach ($this->getReader()->getSheetIterator() as $sheet) {
                    yield from $this->spreadsheet->getSheetRows($sheet->getName());
                }
            }

            $this->getReader()?->close();
        } catch (ReaderNotOpenedException|SheetNotFoundException|WriterNotOpenedException|InvalidSheetNameException $throwable) {
            throw new ExtractorException(
                "Failed to read spreadsheet: {$this->filepath}",
                previous: $throwable
            );
        }
    }
}
