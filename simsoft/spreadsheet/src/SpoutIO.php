<?php

namespace Simsoft\Spreadsheet;

use Box\Spout\Common\Entity\Cell;
use Box\Spout\Common\Entity\Style\Style;
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
use Box\Spout\Writer\Exception\InvalidSheetNameException;
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
     * Determine is current file is for reading.
     *
     * @return bool
     */
    public function isReader(): bool
    {
        return $this->reader instanceof ReaderAbstract;
    }

    /**
     * Determine is current file is for writing.
     *
     * @return bool
     */
    public function isWriter(): bool
    {
        return $this->writer instanceof WriterMultiSheetsAbstract;
    }

    /**
     * Set active sheet.
     *
     * @throws ReaderNotOpenedException
     * @throws WriterNotOpenedException
     * @throws SheetNotFoundException
     * @throws InvalidSheetNameException
     */
    public function sheet(string|int $sheetNameOrIndex = ''): static
    {
        if ($sheet = $this->sheetExists($sheetNameOrIndex, true)) {
            if ($this->isReader()) {
                $this->activeSheet = &$sheet;
                $this->headers = [];
            } elseif ($this->writer instanceof WriterMultiSheetsAbstract) {
                $this->writer->setCurrentSheet($this->activeSheet = $sheet);
            }
        } elseif ($this->writer instanceof WriterMultiSheetsAbstract) {
            $sheet = $this->writer->addNewSheetAndMakeItCurrent();
            $sheet->setName($sheetNameOrIndex);
            $this->activeSheet = $this->writer->getCurrentSheet();
        }
        return $this;
    }

    /**
     * Determine if sheet exists.
     *
     * @param string|int $sheetNameOrIndex
     * @param bool $returnSheet
     * @return bool|Sheet|SheetInterface
     * @throws ReaderNotOpenedException
     * @throws WriterNotOpenedException
     * @throws SheetNotFoundException
     */
    public function sheetExists(string|int $sheetNameOrIndex, bool $returnSheet = false): bool|Sheet|SheetInterface
    {
        if ($this->reader instanceof ReaderAbstract) {
            foreach ($this->reader->getSheetIterator() as $sheet) {
                if ($sheet->getIndex() === $sheetNameOrIndex || $sheet->getName() === $sheetNameOrIndex) {
                    return $returnSheet ? $sheet : true;
                }
            }

            throw new SheetNotFoundException("Sheet: '$sheetNameOrIndex' is not found!");

        } elseif ($this->writer instanceof WriterMultiSheetsAbstract) {
            foreach ($this->writer->getSheets() as $sheet) {
                if ($sheet->getIndex() === $sheetNameOrIndex || $sheet->getName() === $sheetNameOrIndex) {
                    return $returnSheet ? $sheet : true;
                }
            }
        }

        return false;
    }

    /**
     * @throws ReaderNotOpenedException
     * @throws SheetNotFoundException
     * @throws WriterNotOpenedException|InvalidSheetNameException
     */
    public function getSheetRows(string|int|null $sheetNameOrIndex = null): Iterator
    {
        foreach ($this->sheet($sheetNameOrIndex ??= 'Sheet1')->activeSheet->getRowIterator() as $index => $row) {
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

            yield $sheetNameOrIndex => $data;
        }
    }

    /**
     * Add Row.
     *
     * @param array $data
     * @param Style|null $style
     * @return void
     * @throws IOException
     * @throws WriterNotOpenedException
     */
    public function addRow(array $data, ?Style $style = null): void
    {
        $this->writer->addRow(WriterEntityFactory::createRowFromArray($data, $style));
    }
}
