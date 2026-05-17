<?php

namespace Simsoft\DataFlow\Loaders;

use Exception;
use Iterator;
use OpenSpout\Common\Exception\IOException;
use OpenSpout\Common\Exception\UnsupportedTypeException;
use OpenSpout\Reader\Exception\ReaderNotOpenedException;
use OpenSpout\Writer\Exception\InvalidSheetNameException;
use OpenSpout\Writer\Exception\SheetNotFoundException;
use OpenSpout\Writer\Exception\WriterNotOpenedException;
use OpenSpout\Writer\WriterInterface;
use Simsoft\DataFlow\Exceptions\LoaderException;
use Simsoft\DataFlow\Loader;
use Simsoft\Spreadsheet\SpoutIO;

/**
 * SpoutLoader class.
 */
class SpoutLoader extends Loader
{
    /** @var SpoutIO The spreadsheet object. */
    protected SpoutIO $spreadsheet;

    /** @var bool Auto detect headers. */
    protected bool $detectHeaders = true;

    /** @var array<string, string[]> Headers. */
    protected array $headers = [];

    /** @var string Default file extension. */
    protected string $extension = 'xlsx';

    /**
     * Constructor.
     *
     * @param string $filepath
     * @param string $defaultSheetName
     * @throws LoaderException
     */
    public function __construct(protected string $filepath, protected string $defaultSheetName = 'Sheet1')
    {
        try {
            if (str_contains($this->filepath, '.')) {
                [$this->filepath, $this->extension] = explode('.', $this->filepath);
            }

            if ($timestamp = date_create()) {
                $this->filepath .= '_' . $timestamp->format('Ymd-His');
            }

            $this->filepath .= '.' . $this->extension;

            $this->spreadsheet = SpoutIO::createFromFile($this->filepath);
        } catch (IOException|UnsupportedTypeException $throwable) {
            throw new LoaderException(
                "Failed to create file for writing: {$this->filepath}",
                previous: $throwable
            );
        }
    }


    /**
     * Auto detect headers.
     *
     * @return $this
     */
    public function withoutHeaders(): static
    {
        $this->detectHeaders = false;
        return $this;
    }

    /**
     * Get writer.
     *
     * @return WriterInterface|null
     */
    public function &getWriter(): ?WriterInterface
    {
        return $this->spreadsheet->getWriter();
    }

    /**
     * Add headers.
     *
     * @param string[] $headers
     * @param string|null $sheetName
     * @return $this
     * @throws IOException
     * @throws InvalidSheetNameException
     * @throws ReaderNotOpenedException
     * @throws SheetNotFoundException
     * @throws WriterNotOpenedException
     */
    public function withHeaders(array $headers, ?string $sheetName = null): static
    {
        $sheetName ??= $this->defaultSheetName;
        $this->headers[$sheetName] = $headers;

        $this->spreadsheet
            ->sheet($sheetName)
            ->addRow(
                array_is_list($headers) ? $headers : array_values($headers),
                bold: true
            );

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @throws IOException
     * @throws ReaderNotOpenedException
     * @throws SheetNotFoundException
     * @throws UnsupportedTypeException
     * @throws WriterNotOpenedException
     * @throws InvalidSheetNameException
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        if ($dataFrame) {
            $headers = [];
            foreach ($dataFrame as $sheetName => $data) {
                is_string($sheetName) || ($sheetName = 'Sheet1');
                !is_array($data) && throw new UnsupportedTypeException('Data must be an array.');

                if (array_is_list($data)) {
                    if (!$this->isDryRun()) {
                        $this->spreadsheet->sheet($sheetName)->addRow($data);
                    }
                    yield $sheetName => $data;
                    continue;
                }

                $this->ensureHeaders($sheetName, $data, $headers);

                if (!$this->isDryRun()) {
                    $this->spreadsheet->sheet($sheetName)->addRow(array_merge($headers[$sheetName], $data));
                }
                yield $sheetName => $data;
            }

            if (!$this->isDryRun()) {
                $this->getWriter()?->close();
            }
        }
    }

    /**
     * Ensure headers are initialized for the given sheet.
     *
     * @param string $sheetName The sheet name.
     * @param array<string, mixed> $data The current row data (used for auto-detection).
     * @param array<string, array<string, null>> &$headers Reference to the headers map.
     * @return void
     * @throws IOException
     * @throws InvalidSheetNameException
     * @throws ReaderNotOpenedException
     * @throws SheetNotFoundException
     * @throws WriterNotOpenedException
     */
    private function ensureHeaders(string $sheetName, array $data, array &$headers): void
    {
        if (!$this->detectHeaders || array_key_exists($sheetName, $headers)) {
            return;
        }

        if (!array_key_exists($sheetName, $this->headers)) {
            $this->withHeaders(array_keys($data), $sheetName);
        }

        if (array_is_list($this->headers[$sheetName])) {
            $headers[$sheetName] = array_combine(
                $this->headers[$sheetName],
                array_fill(0, count($this->headers[$sheetName]), null)
            );
            return;
        }

        foreach ($this->headers[$sheetName] as $fromLabel => $toLabel) {
            $headers[$sheetName][is_string($fromLabel) ? $fromLabel : $toLabel] = null;
        }
    }
}
