<?php

namespace Simsoft\Spreadsheet;

use Iterator;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\CSV\Options as CSVReaderOptions;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use OpenSpout\Reader\Exception\ReaderNotOpenedException;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\Exception\InvalidSheetNameException;
use OpenSpout\Writer\Exception\SheetNotFoundException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\WriterMultiSheetsAbstract;
use OpenSpout\Reader\Common\Creator\ReaderFactory;
use OpenSpout\Writer\Common\Creator\WriterFactory;

/**
 * Class SpoutIO
 *
 * Read/ write XLSX, CSV, ODS files with OpenSpout.
 */
class SpoutIO
{
    /** @var SheetInterface|Sheet|null Current active sheet object */
    protected SheetInterface|Sheet|null $activeSheet = null;

    /** @var bool Indicate headers exists. */
    protected bool $headerExists = false;

    /** @var array Headers value. */
    protected array $headers = [];

    /**
     * Constructor
     *
     * @param string $filepath
     * @param ReaderInterface|null $reader
     * @param WriterInterface|null $writer
     * @param string $csvDelimiter
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
     * @param string $filepath
     * @param string $csvDelimiter
     * @return static
     * @throws UnsupportedTypeException|IOException
     */
    public static function readFromFile(string $filepath, string $csvDelimiter = ','): static
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $options = new CSVReaderOptions();
            $options->FIELD_DELIMITER = $csvDelimiter;
            $reader = new CSVReader($options);
        } else {
            $reader = ReaderFactory::createFromFile($filepath);
        }

        return new static($filepath, reader: $reader, csvDelimiter: $csvDelimiter);
    }

    /**
     * Write to file.
     *
     * @param string $filepath
     * @return static
     * @throws UnsupportedTypeException|IOException
     */
    public static function createFromFile(string $filepath): static
    {
        return new static($filepath, writer: WriterFactory::createFromFile($filepath));
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
        return $this->reader instanceof ReaderInterface;
    }

    /**
     * Determine is current file is for writing.
     *
     * @return bool
     */
    public function isWriter(): bool
    {
        return $this->writer instanceof WriterInterface;
    }

    /**
     * Set active sheet.
     *
     * @param string|int $sheetNameOrIndex
     * @return static
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
        if ($this->reader instanceof ReaderInterface) {
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
     * Get sheet rows.
     *
     * @param string|int|null $sheetNameOrIndex
     * @return Iterator
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
     * @param bool $bold
     * @return void
     * @throws IOException
     * @throws WriterNotOpenedException
     */
    public function addRow(array $data, ?Style $style = null, bool $bold = false): void
    {
        if ($style === null && $bold) {
            $style = new Style();
            $style->setFontBold();
        }

        $row = Row::fromValues($data, $style);
        $this->writer->addRow($row);
    }
}
