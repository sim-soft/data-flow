<?php

namespace Simsoft\DataFlow\Extractors;

use Simsoft\DataFlow\Extractor;
use Exception;
use Iterator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;

/**
 * SpreadsheetExtractor class
 */
class SpreadsheetExtractor extends Extractor
{
    protected Spreadsheet $spreadsheet;

    /** @var string[] Set headers. */
    protected array $headers = [];

    /** @var string|null Select sheet name. */
    protected ?string $sheetName = null;

    /**
     * Constructor.
     *
     * @param string $filePath File path.
     * @throws Exception
     */
    final public function __construct(protected string $filePath)
    {
        if (!file_exists($this->filePath)) {
            throw new Exception("File not found: $filePath");
        }

        $this->spreadsheet = IOFactory::load($filePath, IReader::READ_DATA_ONLY | IReader::IGNORE_EMPTY_CELLS);
    }

    /**
     * Factory method.
     *
     * @param string $filePath File path.
     * @return static
     * @throws Exception
     */
    public static function from(string $filePath): static
    {
        return new static($filePath);
    }

    /**
     * Set headers.
     *
     * @param string[] $headers
     * @return $this
     */
    public function headers(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set current working CSV.
     *
     * @param string|null $sheetName
     * @return $this
     */
    public function sheet(?string $sheetName): static
    {
        $this->sheetName = $sheetName;
        return $this;
    }

    /**
     * Get sheet row.
     *
     * @param int $startRow
     * @param int|null $endRow
     * @return RowIterator
     */
    protected function getSheetRow(int $startRow = 1, ?int $endRow = null): RowIterator
    {
        return $this->sheetName
            ? $this->spreadsheet->setActiveSheetIndexByName($this->sheetName)->getRowIterator($startRow, $endRow)
            : $this->spreadsheet->getActiveSheet()->getRowIterator($startRow, $endRow);

    }

    /**
     * {@inheritdoc}
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        foreach ($this->getSheetRow() as $rowIndex => $row) {
            $data = [];
            foreach ($row->getColumnIterator() as $column) {
                $data[] = $column->getValue();
            }

            if ($this->headers == [] && $rowIndex == 1) {
                $this->headers = $data;
                continue;
            }

            yield $this->headers ? array_combine($this->headers, $data) : $data;
        }
    }
}
