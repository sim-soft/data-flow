<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Loaders;

use Exception;
use InvalidArgumentException;
use Iterator;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;

/**
 * Preview Data in the pipeline.
 *
 * Output is written to a configurable stream resource (default: STDOUT) so
 * the loader can be embedded and tested without relying on output buffering.
 */
class Preview extends Loader
{
    use CallableDataFrame;

    /**
     * @var resource Stream resource to write output to.
     */
    protected $stream;

    /**
     * Constructor.
     *
     * @param resource|null $stream Optional writable stream resource (default: STDOUT).
     *
     * @throws InvalidArgumentException When $stream is provided but is not a resource.
     */
    public function __construct($stream = null)
    {
        if ($stream === null) {
            $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Preview $stream must be a writable resource.');
        }

        $this->stream = $stream;
    }

    /**
     * Write a single key/value entry to the configured stream.
     */
    protected function writeEntry(mixed $key, mixed $value): void
    {
        fwrite($this->stream, 'Key: ' . var_export($key, true) . PHP_EOL);
        fwrite($this->stream, 'Value: ' . var_export($value, true) . PHP_EOL);
        fwrite($this->stream, PHP_EOL);
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        return $this->call($dataFrame, function (mixed $data, mixed $index) {

            if ($data instanceof Iterator) {
                foreach ($data as $rowIndex => $row) {
                    $this->writeEntry($rowIndex, $row);
                }
                return Signal::Next;
            }

            $this->writeEntry($index, $data);

            return Signal::Next;
        });
    }
}
