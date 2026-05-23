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
 * Visualize class.
 *
 * Visualize data in the pipeline. Output is written to a configurable stream
 * resource (default: STDOUT) so the loader can be embedded and tested without
 * relying on output buffering.
 */
class Visualize extends Loader
{
    use CallableDataFrame;

    public const string FORMAT_JSON = 'json';
    public const string FORMAT_OBJ = 'object';

    /**
     * @var resource Stream resource to write output to.
     */
    protected $stream;

    /**
     * Constructor.
     *
     * @param string $format Output format. Default: json
     * @param resource|null $stream Optional writable stream resource (default: STDOUT).
     *
     * @throws InvalidArgumentException When $stream is provided but is not a resource.
     */
    public function __construct(protected string $format = self::FORMAT_JSON, $stream = null)
    {
        if ($stream === null) {
            $stream = defined('STDOUT') ? STDOUT : fopen('php://stdout', 'w');
        }

        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Visualize $stream must be a writable resource.');
        }

        $this->stream = $stream;
    }

    /**
     * Output data content.
     *
     * @param mixed $data
     * @return void
     */
    protected function output(mixed $data): void
    {
        if ($this->format === static::FORMAT_JSON && is_array($data)) {
            fwrite($this->stream, json_encode($data) . PHP_EOL);
            return;
        }

        fwrite($this->stream, var_export($data, true) . PHP_EOL);
    }

    /**
     * Process data frame.
     *
     * @param Iterator $dataFrame
     * @return Iterator
     */
    protected function processDataFrame(Iterator $dataFrame): Iterator
    {
        foreach ($dataFrame as $index => $data) {
            $this->output($data);
            yield $index => $data;
        }
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame = null): Iterator
    {
        return $this->call($dataFrame, function (mixed $data, mixed $index) {

            if ($data instanceof Iterator) {
                yield from $this->processDataFrame($data);
                return Signal::Next;
            }

            $this->output($data);
            yield $index => $data;
            return Signal::Next;
        });
    }
}
