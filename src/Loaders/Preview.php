<?php

namespace Simsoft\DataFlow\Loaders;

use Exception;
use Iterator;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Loader;
use Simsoft\DataFlow\Traits\CallableDataFrame;

/**
 * Preview Data in the pipeline.
 */
class Preview extends Loader
{
    use CallableDataFrame;

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        return $this->call($dataFrame, function (mixed $data, mixed $index) {

            print 'Payload: ';
            var_dump($this->getPayload());

            if ($data instanceof Iterator) {
                foreach ($data as $rowIndex => $row) {
                    print "Key: ";
                    var_dump($rowIndex);
                    print 'Value: ';
                    var_dump($row);
                    print PHP_EOL;
                }
                return Signal::Next;
            }

            print "Key: ";
            var_dump($index);
            print 'Value: ';
            var_dump($data);
            print PHP_EOL;

            return Signal::Next;
        });
    }
}
