<?php

namespace Simsoft\DataFlow\Loaders;

use Box\Spout\Common\Exception\IOException;
use Box\Spout\Common\Exception\UnsupportedTypeException;
use Box\Spout\Reader\Exception\ReaderNotOpenedException;
use Box\Spout\Writer\Common\Creator\Style\StyleBuilder;
use Box\Spout\Writer\Exception\InvalidSheetNameException;
use Box\Spout\Writer\Exception\SheetNotFoundException;
use Box\Spout\Writer\Exception\WriterNotOpenedException;
use Box\Spout\Writer\WriterInterface;
use Exception;
use Iterator;
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

    /** @var array Headers. */
    protected array $headers = [];

    /**
     * Constructor.
     *
     * @param string $filepath
     * @param string $defaultSheetName
     */
    public function __construct(protected string $filepath, protected string $defaultSheetName = 'Sheet1')
    {
        try {
            $this->spreadsheet = SpoutIO::createFromFile($filepath);
        } catch (IOException|UnsupportedTypeException $throwable) {
            error_log($throwable->getMessage());
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
     * Set temporary path.
     *
     * @param string $path
     * @return $this
     */
    public function tempFolder(string $path): static
    {
        $this->getWriter()->setTempFolder($path);
        return $this;
    }

    /**
     * Add headers.
     *
     * @param array $headers
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
                (new StyleBuilder())->setFontBold()->build()
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
                    $this->spreadsheet->sheet($sheetName)->addRow($data);
                    yield $sheetName => $data;
                    continue;
                }

                if ($this->detectHeaders && !array_key_exists($sheetName, $headers)) {
                    if (!array_key_exists($sheetName, $this->headers)) {
                        $this->withHeaders(array_keys($data), $sheetName);
                    }

                    if (array_is_list($this->headers[$sheetName])) {
                        $headers[$sheetName] = array_combine(
                            array_values($this->headers[$sheetName]),
                            array_fill(0, count($this->headers[$sheetName]), null)
                        );
                    } else {
                        foreach ($this->headers[$sheetName] as $fromLabel => $toLabel) {
                            $headers[$sheetName][is_string($fromLabel) ? $fromLabel : $toLabel] = null;
                        }
                    }
                }

                $this->spreadsheet->sheet($sheetName)->addRow(array_merge($headers[$sheetName], $data));
                yield $sheetName => $data;
            }

            $this->getWriter()->close();
        }
    }
}
