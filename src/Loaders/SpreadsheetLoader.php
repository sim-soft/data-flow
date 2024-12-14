<?php

namespace Simsoft\DataFlow\Loaders;

use Exception;
use Iterator;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;
use Simsoft\Spreadsheet\SpreadsheetIO;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * SpreadsheetLoader class.
 *
 * Export data to CSV/ XLSX file.
 */
class SpreadsheetLoader extends Loader
{
    use CallableDataFrame;

    /** @var string Default file extension. */
    protected string $extension = 'xlsx';

    /** @var bool Append to file, which will disable filename timestamp. Default: false. */
    protected bool $append = false;

    /** @var SpreadsheetIO SpreadsheetIO Object */
    protected SpreadsheetIO $spreadsheet;

    /** @var string|null Sheet name. */
    protected ?string $sheetName = null;

    /** @var int|null Sheet index. */
    protected ?int $sheetIndex = null;

    /**
     * Constructor.
     *
     * @param string $filePath Destination file path.
     * @param string $docType Document type. Options: Xlsx, Csv. Default: Xlsx.
     * @param string|null $cacheDir Cache directory path, if provided will enable cache. Default: null
     */
    public function __construct(
        protected string $filePath,
        protected string  $docType = 'Xlsx',
        protected ?string $cacheDir = null,
    )
    {
        if (str_contains($this->filePath, '.')) {
            [$this->filePath, $this->extension] = explode('.', $this->filePath);
        }
    }

    /**
     * Enable append to file without timestamp.
     *
     * @return $this
     */
    public function append(): static
    {
        $this->append = true;
        return $this;
    }

    /**
     * Set active sheet name.
     *
     * @param string $name Sheet name.
     * @param int|null $sheetIndex Sheet index.
     * @return $this
     */
    public function sheet(string $name, ?int $sheetIndex = null): static
    {
        $this->sheetName = $name;
        $this->sheetIndex = $sheetIndex;
        return $this;
    }

    /**
     * Set file path.
     * @return void
     */
    protected function setFilepath(): void
    {
        if (!$this->append && ($timestamp = date_create())) {
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
        $this->setFilepath();

        $this->spreadsheet = new SpreadsheetIO(
            cache: $this->cacheDir ? new Psr16Cache(new FilesystemAdapter(directory: $this->cacheDir)) : null
        );

        if ($this->sheetName) {
            $this->spreadsheet->sheetName($this->sheetName, $this->sheetIndex);
        }

        return $this->call($dataFrame, function (mixed $data) {
            if ($data instanceof Iterator) {
                $count = 0;
                foreach ($data as $row) {
                    $this->spreadsheet->addRow($row);
                    if (++$count >= 10) { // write to file every 10 rows. Avoid memory exhaustion.
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
