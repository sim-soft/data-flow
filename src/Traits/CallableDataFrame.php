<?php

namespace Simsoft\DataFlow\Traits;

use Simsoft\DataFlow\Enums\Signal;
use Closure;
use Exception;
use Iterator;

/**
 * CallableDataFrame trait
 *
 * Walk through data frame with callback method.
 */
trait CallableDataFrame
{
    /**
     * Walk through data frame with callback method.
     *
     * @param Iterator|null $dataFrame
     * @param Closure $callback
     * @return Iterator
     * @throws Exception
     */
    public function call(?Iterator $dataFrame, Closure $callback): Iterator
    {
        if ($dataFrame !== null) {
            foreach ($dataFrame as $key => $data) {
                $fail = null;
                $feedback = call_user_func_array($callback, [
                    &$data,
                    $key,
                    function (string $failMessage) use (&$fail) {
                        $fail = $failMessage;
                    }
                ]);

                if (is_string($fail)) {
                    throw new Exception($fail);
                }

                if ($feedback === Signal::Next) {
                    continue;
                }

                if ($feedback === Signal::Stop) {
                    break;
                }

                if ($feedback instanceof Iterator) {
                    yield from $feedback;
                    continue;
                }

                yield $feedback;
            }
        }
    }
}
