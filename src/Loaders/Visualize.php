<?php

namespace Simsoft\DataFlow\Loaders;

use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;
use Exception;
use Iterator;

/**
 * Visualize class.
 *
 * Visualize data in the pipeline.
 */
class Visualize extends Loader
{
    use CallableDataFrame;

    const FORMAT_JSON = 'json';
    const FORMAT_OBJ = 'object';

    /**
     * Constructor.
     *
     * @param string $format Output format. Default: json
     */
    public function __construct(protected string $format = self::FORMAT_JSON)
    {

    }

    /**
     * Output data content.
     *
     * @param mixed $data
     * @return void
     */
    protected function output(mixed $data): void
    {
        $this->format === static::FORMAT_JSON && is_array($data) ? print json_encode($data) . PHP_EOL : var_dump($data);
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
    public function __invoke(?Iterator $dataFrame): Iterator
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
