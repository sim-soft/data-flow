<?php

namespace Simsoft\DataFlow\Transformers;

use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Traits\CallableDataFrame;
use Simsoft\DataFlow\Transformer;
use Exception;
use Iterator;

/**
 * Preview Data in the pipeline.
 */
class Preview extends Transformer
{
    use CallableDataFrame;

    /**
     * Constructor.
     * @param int $max Maximum number of rows to preview. Default: 1.
     * @throws Exception
     */
    public function __construct(protected int $max = 1)
    {
        if ($this->max <= 0) {
            throw new Exception('Max number of previews must be greater than 0');
        }
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        $count = 0;
        return $this->call($dataFrame, function (mixed $data, mixed $index) use (&$count) {

            print 'Payload: ';
            var_dump($this->getPayload());

            if ($data instanceof Iterator) {
                foreach ($data as $rowIndex => $row) {
                    print "Key: ";
                    var_dump($rowIndex);
                    print 'Value: ';
                    var_dump($row);
                    print "\n";
                    if (++$count > $this->max) {
                        return Signal::Stop;
                    }
                }
                return Signal::Next;
            }

            print "Key: ";
            var_dump($index);
            print 'Value: ';
            var_dump($data);
            print "\n";

            if (++$count >= $this->max) {
                return Signal::Stop;
            }

            return Signal::Next;
        });
    }
}
