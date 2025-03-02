<?php

namespace Simsoft\Spreadsheet;

use Box\Spout\Common\Entity\Cell;
use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Reader\Common\Creator\ReaderEntityFactory;
use Box\Spout\Reader\CSV\Reader as CSVReader;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Reader\ReaderAbstract;
use Box\Spout\Reader\ReaderInterface;
use Box\Spout\Reader\SheetInterface;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Box\Spout\Writer\Common\Entity\Sheet;
use Box\Spout\Writer\Exception\SheetNotFoundException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Box\Spout\Writer\WriterInterface;
use Box\Spout\Writer\WriterMultiSheetsAbstract;
use Iterator;

/**
 * Class SpoutIO
 *
 * Read/ write XLSX, CSV, ODS files with open/spout.
 */
class SpoutIO
{
    protected SheetInterface|Sheet|null $activeSheet = null;

    protected bool $headerExists = false;
    protected array $headers = [];

    /**
     * Constructor
     *
     * @throws IOException
     */
    final public function __construct(
        protected string           $filepath,
        protected ?ReaderInterface $reader = null,
        protected ?WriterInterface $writer = null,
        string                     $csvDelimiter = ','
    )
    {
        if ($this->reader) {
            if ($this->reader instanceof CSVReader) {
                $this->reader->setFieldDelimiter($csvDelimiter);
            }
            $this->reader->open($this->filepath);
        }

        $this->writer?->openToFile($this->filepath);
    }

    /**
     * Get reader.
     *
     * @return ReaderInterface|null
     */
    public function &getReader(): ?ReaderInterface
    {
        return $this->reader;
    }

    /**
     * Get writer.
     *
     * @return WriterInterface|null
     */
    public function &getWriter(): ?WriterInterface
    {
        return $this->writer;
    }

    /**
     * Read from file.
     *
     * @throws UnsupportedTypeException|IOException
     */
    public static function readFromFile(string $filepath, string $csvDelimiter = ','): static
    {
        return new static($filepath, reader: ReaderEntityFactory::createReaderFromFile($filepath), csvDelimiter: $csvDelimiter);
    }

    /**
     * Write to file.
     *
     * @throws UnsupportedTypeException|IOException
     */
    public static function createFromFile(string $filepath): static
    {
        return new static($filepath, writer: WriterEntityFactory::createWriterFromFile($filepath));
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
     * Set active sheet.
     *
     * @throws ReaderNotOpenedException
     * @throws WriterNotOpenedException
     * @throws SheetNotFoundException
     */
    public function sheet(string|int $sheetNameOrIndex = ''): static
    {
        if ($this->reader instanceof ReaderAbstract) {
            foreach ($this->reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() === $sheetNameOrIndex || $sheet->getName() === $sheetNameOrIndex) {
                    $this->activeSheet = &$sheet;
                    $this->headers = [];
                    break;
                }
            }
        } elseif ($this->writer instanceof WriterMultiSheetsAbstract) {
            foreach ($this->writer->getSheets() as $sheet) {
                if ($sheet->getIndex() === $sheetNameOrIndex || $sheet->getName() === $sheetNameOrIndex) {
                    $this->writer->setCurrentSheet($this->activeSheet = $sheet);
                    break;
                }
            }

            if ($this->activeSheet === null) {
                $this->writer->addNewSheetAndMakeItCurrent();
                $this->activeSheet = $this->writer->getCurrentSheet();
            }
        }
        return $this;
    }


    /**
     * @throws ReaderNotOpenedException
     * @throws SheetNotFoundException
     * @throws WriterNotOpenedException
     */
    public function getSheetRows(string|int|null $sheetNameOrIndex = null): Iterator
    {
        foreach ($this->sheet($sheetNameOrIndex ?? '')->activeSheet->getRowIterator() as $index => $row) {
            if ($index === 1 && $this->headerExists && $this->headers === []) {
                /** @var Cell $cell */
                foreach ($row->getCells() as $cell) {
                    $this->headers[] = $cell->getValue();
                }
                continue;
            }

            $data = [];
            if ($this->headers) {
                foreach ($row->getCells() as $cellIndex => $cell) {
                    if ($this->headers[$cellIndex] ?? false) {
                        $data[$this->headers[$cellIndex]] = $cell->getValue();
                    }
                }
            } else {
                foreach ($row->getCells() as $cell) {
                    $data[] = $cell->getValue();
                }
            }

            yield $data;
        }
    }

    /**
     * Add Row.
     *
     * @param array $data
     * @return void
     */
    public function addRow(array $data): void
    {
        $this->activeSheet = $this->writer->getCurrentSheet();
    }

}
