<?php

namespace Simsoft\DataFlow\Loaders;

use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;
use Exception;
use Iterator;

/**
 * VisualLoader class.
 */
class VisualLoader extends Loader
{
    use CallableDataFrame;

    /**
     * Constructor.
     *
     * @param int $max Maximum rows to be displayed. Default: -1 (all)
     * @param string $format Output format. Default: json
     */
    public function __construct(protected int $max = -1, protected string $format = 'json')
    {

    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        $count = 0;
        return $this->call($dataFrame, function (mixed $data, mixed $index) use (&$count) {
            if ($data instanceof Iterator) {
                foreach ($data as $row) {
                    $this->format === 'json' ? print json_encode($row) . "\n" : var_dump($row); // Display the row
                    yield $row;
                    if (++$count > $this->max) {
                        return Signal::Stop;
                    }
                }
                return Signal::Next;
            }

            $this->format === 'json' ? print json_encode($data) . "\n" : var_dump($data); // Display the data
            yield $data;

            if (++$count >= $this->max) {
                return Signal::Stop;
            }

            return Signal::Next;
        });
    }
}
