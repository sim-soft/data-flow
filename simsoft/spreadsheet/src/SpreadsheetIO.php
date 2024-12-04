<?php

namespace Simsoft\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Settings;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Style;
use PhpOffice\PhpSpreadsheet\Worksheet\ColumnDimension;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * class SpreadsheetIO
 *
 */
class SpreadsheetIO
{
    /** @var null|Spreadsheet The Spreadsheet obj */
    protected ?Spreadsheet $spreadsheet = null;

    /** @var array Sheet headers. */
    protected array $sheetHeader = [];

    /** @var array Sheet next row. */
    protected array $sheetNextRow = [];

    /**
     * Constructor
     */
    public function __construct(
        protected string          $docType = 'Xlsx',
        protected ?string $title = null,
        protected ?string $creator = null,
        protected ?string $company = null,
        protected ?string $subject = null,
        protected ?string $description = null,
        protected ?string $category = null,
        protected ?string $lastModifiedBy = null,
        protected ?string $manager = null,
        protected ?bool           $readOnly = false,
        protected ?CacheInterface $cache = null,
    )
    {
        if ($cache) {
            Settings::setCache($cache);
        }

        $this->spreadsheet = new Spreadsheet();

        if ($title) {
            $this->spreadsheet->getProperties()->setTitle($title);
        }

        if ($creator) {
            $this->spreadsheet->getProperties()->setCreator($creator);
        }

        if ($company) {
            $this->spreadsheet->getProperties()->setCompany($company);
        }

        if ($subject) {
            $this->spreadsheet->getProperties()->setSubject($subject);
        }

        if ($description) {
            $this->spreadsheet->getProperties()->setDescription($description);
        }

        if ($category) {
            $this->spreadsheet->getProperties()->setCategory($category);
        }

        if ($lastModifiedBy) {
            $this->spreadsheet->getProperties()->setLastModifiedBy($lastModifiedBy);
        }

        if ($manager) {
            $this->spreadsheet->getProperties()->setManager($manager);
        }

        if ($readOnly) {
            $this->spreadsheet->getSecurity()->setLockWindows(true);
            $this->spreadsheet->getSecurity()->setLockStructure(true);
        }
    }

    /**
     * Load existing spreadsheet path.
     *
     * @param string $filePath
     * @return $this
     */
    public function load(string $filePath): static
    {
        $this->spreadsheet = IOFactory::load($filePath);
        return $this;
    }

    /**
     * Is current sheet has headers.
     *
     * @return bool
     */
    public function hasHeaders(): bool
    {
        return !empty($this->sheetHeader[$this->spreadsheet->getActiveSheetIndex()]);
    }

    /**
     * Add header names at the first row.
     *
     * @param ...$headerNames
     * @return $this
     */
    public function header(...$headerNames): static
    {
        $this->addRow($headerNames, ['font' => ['bold' => true]]);
        $this->sheetHeader[$this->spreadsheet->getActiveSheetIndex()] = $headerNames;
        return $this;
    }

    /**
     * Set active sheet name.
     *
     * @param string $name
     * @param int|null $sheetIndex
     * @return $this
     */
    public function sheetName(string $name, ?int $sheetIndex = null): static
    {
        if (!$this->spreadsheet->sheetNameExists($name)) {
            $this->createSheet($sheetIndex)->setTitle($name);
        }

        $this->spreadsheet->setActiveSheetIndexByName($name);
        return $this;
    }

    /**
     * Populate row with data value.
     *
     * @param array $data The data to be written to each column of the row.
     * @param array $style
     * @return void
     */
    public function addRow(array $data, array $style = []): void
    {
        $row = array_key_exists($this->spreadsheet->getActiveSheetIndex(), $this->sheetNextRow)
            ? $this->sheetNextRow[$this->spreadsheet->getActiveSheetIndex()]
            : 1;

        if ($data) {
            if (array_is_list($data)) {
                foreach ($data as $column => $value) {
                    //$coordinate = $this->getColumnLabel($column) . $row;
                    $coordinate = [$column + 1, $row];
                    $this->getActiveSheet()->setCellValue($coordinate, $value);
                    if ($style) {
                        $this->setStyle($coordinate, $style);
                    }
                }
            } else {
                if (!$this->hasHeaders()) {
                    $this->header(...array_keys($data));
                    ++$row;
                }

                $headers = array_flip($this->sheetHeader[$this->spreadsheet->getActiveSheetIndex()]);
                foreach ($data as $header => $value) {
                    //$coordinate = $this->getColumnLabel($headers[$header]) . $row;
                    $coordinate = [$headers[$header] + 1, $row];
                    $this->getActiveSheet()->setCellValue($coordinate, $value);
                    if ($style) {
                        $this->setStyle($coordinate, $style);
                    }
                }
            }
        }

        $this->sheetNextRow[$this->spreadsheet->getActiveSheetIndex()] = ++$row;
    }

    /**
     * Get column label.
     *
     * @param int $number
     * @return string
     */
    protected function getColumnLabel(int $number): string
    {
        $result = '';
        while (++$number > 0) {
            $result = chr(65 + (--$number % 26)) . $result;
            $number = intdiv($number, 26);
        }
        return $result;
    }

    /**
     * Populate specific row with data value.
     *
     * @param int $row The row position to be written.
     * @param array $data The data to be written to the specific row.
     * @return void
     */
    public function setRow(int $row, array $data): void
    {
        foreach ($data as $column => $value) {
            $this->spreadsheet->getActiveSheet()
                //->setCellValue($this->getColumnLabel($column) . $row, $value);
                ->setCellValue([$column + 1, $row], $value);
        }
    }

    /**
     * Populate column with data.
     *
     * @param int $column The column position to be populated.
     * @param array $data The data values to be written to each row of the column.
     * @return void
     */
    public function fillColumn(int $column, array $data): void
    {
        $row = $this->hasHeaders() ? 2 : 1;

        foreach ($data as $value) {
            $this->spreadsheet->getActiveSheet()->setCellValue($column . ($row++), $value);
        }
    }

    /**
     * Set value for cell.
     *
     * @param int $column Column position of the cell.
     * @param int $row Row position of the cell.
     * @param mixed $value The value to be written to the cell.
     * @return void
     */
    public function setValue(int $column, int $row, mixed $value): void
    {
        $this->getActiveSheet()->setCellValue("$column$row", $value);
    }


    /**
     * @return Worksheet
     */
    public function getActiveSheet(): Worksheet
    {
        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Get writer for save
     *
     * @return IWriter
     */
    public function getWriter(): IWriter
    {
        return IOFactory::createWriter($this->spreadsheet, $this->docType);
    }

    /**
     * Save to file.
     *
     * @param string $filename File name to be saved to.
     * @return void
     */
    public function saveAs(string $filename): void
    {
        try {
            (new Xlsx($this->spreadsheet))
                ->setOffice2003Compatibility(true)
                ->save($filename);
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * @param string $filename
     * @return void
     */
    public function download(string $filename): void
    {
        ob_end_clean();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        try {
            $this->getWriter()->save('php://output');
            exit();
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
    }

    /**
     * Set style by coordinate.
     *
     * Example style:
     * [
     *  'fill' => [
     *      'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
     *      'rotation' => 90,
     *      'startColor' => ['argb' => 'FFA0A0A0'],
     *      'endColor' => ['argb' => 'FFFFFFFF'],
     *      'color' => ['rgb' => '808080'],
     *  ],
     *  'font' => [
     *      'name' => 'Arial',
     *      'bold' => true,
     *      'italic' => false,
     *      'superscript' => false,
     *      'subscript' => false,
     *      'underline' => Font::UNDERLINE_DOUBLE,
     *      'strikethrough' => false,
     *      'color' => ['rgb' => '808080'],
     *      'size' => 10,
     *  ],
     * 'borders' => [
     *      'left' => [
     *            'borderStyle' => Border::BORDER_DASHDOT,
     *            'color' => ['rgb' => '808080']
     *        ]
     *      'right' => [
     *            'borderStyle' => Border::BORDER_DASHDOT,
     *            'color' => ['rgb' => '808080']
     *        ]
     *      'top' => [
     *           'borderStyle' => Border::BORDER_DASHDOT,
     *           'color' => ['rgb' => '808080']
     *       ]
     *      'bottom' => [
     *          'borderStyle' => Border::BORDER_DASHDOT,
     *          'color' => ['rgb' => '808080']
     *      ],
     *      'diagonal' => [
     *            'borderStyle' => Border::BORDER_DASHDOT,
     *            'color' => ['rgb' => '808080']
     *       ],
     *      'diagonalDirection' => [
     *             'diagonalDirection' => Border::DIAGONAL_NONE,
     *        ],
     *      'allBorders' => [
     *           'borderStyle' => Border::BORDER_DASHDOT,
     *           'color' => ['rgb' => '808080']
     *       ],
     * ],
     * 'alignment' => [
     *      'horizontal' => Alignment::HORIZONTAL_CENTER,
     *      'vertical' => Alignment::VERTICAL_CENTER,
     *      'textRotation' => -165
     *      'wrapText' => true,
     *      'shrinkToFit' => false,
     *      'indent' => 0,
     *      'readOrder' => 0, // 0, 1
     *  ],
     *  'numberFormat' => [
     *      'formatCode' => 'General', //'€ #,##0;€ -#,##0', NumberFormat::FORMAT_NUMBER_00
     *  ],
     *  'protection' => [
     *      'locked' => Protection::PROTECTION_UNPROTECTED,
     *      'hidden' => Protection::PROTECTION_PROTECTED
     *  ]
     *  'quotePrefix' => true,
     *
     * ]
     * @link https://phpspreadsheet.readthedocs.io/en/latest/topics/recipes/#setting-the-default-style-of-a-workbook
     * @throws Exception
     */
    public function setStyle($cellCoordinate, $style = []): void
    {
        if ($style) {
            $this->getActiveSheet()->getStyle($cellCoordinate)->applyFromArray($style);
        }
    }

    public function getCell(string $coordinate): Cell
    {
        return $this->getActiveSheet()->getCell($coordinate);
    }

    public function getColumnDimension(string $column): ColumnDimension
    {
        return $this->getActiveSheet()->getColumnDimension($column);
    }

    /**
     * Get new worksheet.
     *
     * @param int|null $sheetIndex
     * @return Worksheet
     */
    public function createSheet(?int $sheetIndex = null): Worksheet
    {
        return $this->spreadsheet->createSheet($sheetIndex);
    }

    /**
     * @param string $title
     * @return void
     */
    public function setSheetTitle(string $title): void
    {
        $this->getActiveSheet()->setTitle($title);
    }

    /**
     * Set active sheet.
     *
     * @param int|string $index Sheet index/ sheet name to be activated.
     * @return $this
     */
    public function setActiveSheet(int|string $index = 0): static
    {
        try {
            if (is_string($index)) {
                $this->spreadsheet->setActiveSheetIndexByName($index);
            } elseif (is_numeric($index)) {
                $this->spreadsheet->setActiveSheetIndex($index);
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
        }
        return $this;
    }

    /**
     * Get highest worksheet row.
     *
     * @return int
     */
    public function getHighestRow(): int
    {
        return $this->getActiveSheet()->getHighestRow();
    }
}
