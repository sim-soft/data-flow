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

            $callback = Closure::bind($callback, $this, get_called_class());

            foreach ($dataFrame as $key => $data) {
                $exception = null;
                $feedback = call_user_func_array($callback, [ // @phpstan-ignore argument.type
                    &$data,
                    &$key,
                    function (string $message) use (&$exception) {
                        $exception = $message;
                        return Signal::Stop;
                    }
                ]);

                if (is_string($exception)) {
                    throw new Exception($exception);
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

                yield $key => $feedback ?? $data;
            }
        }
    }
}
