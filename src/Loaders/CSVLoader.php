<?php

namespace Simsoft\DataFlow\Loaders;

use Exception;
use Iterator;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;
use Simsoft\Spreadsheet\SpreadsheetIO;

/**
 * CSVLoader class.
 *
 * Export data to CSV/ XLSX file.
 */
class CSVLoader extends Loader
{
    use CallableDataFrame;

    /** @var SpreadsheetIO SpreadsheetIO Object */
    protected SpreadsheetIO $spreadsheet;

    /**
     * Constructor.
     *
     * @param string $filePath
     * @param string $extension
     * @param int $max
     */
    public function __construct(
        protected string $filePath,
        protected string $extension = 'csv',
        protected int    $max = -1
    )
    {
        $this->spreadsheet = new SpreadsheetIO();

        if (str_contains($this->filePath, '.')) {
            [$this->filePath, $this->extension] = explode('.', $this->filePath);
        }

        if ($timestamp = date_create()) {
            $this->filePath .= '_' . $timestamp->format('Ymd-His');
        }

        $this->filePath .= '.' . $this->extension;
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        return $this->call($dataFrame, function (mixed $data, int $index) {
            if ($index === $this->max) {
                return Signal::Stop;
            }

            if ($data instanceof Iterator) {
                $count = 0;
                foreach ($data as $row) {
                    $this->spreadsheet->addRow($row);
                    if (++$count >= 20) { // write to file every 20 rows. Avoid memory exhaustion.
                        $this->spreadsheet->saveAs($this->filePath);
                        $count = 0;
                    }
                }

                if ($count) { // write the rest to file.
                    $this->spreadsheet->saveAs($this->filePath);
                }
            } elseif (is_array($data)) {
                $this->spreadsheet->addRow($data);
                $this->spreadsheet->saveAs($this->filePath);
            }

            return Signal::Next;
        });
    }
}
