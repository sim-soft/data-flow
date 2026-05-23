<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Traits;

use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Closure;
use Iterator;

/**
 * CallableDataFrame trait
 *
 * Walk through a data frame with a callback method.
 */
trait CallableDataFrame
{
    /**
     * Walk through a data frame with a callback method.
     *
     * @param Iterator|null $dataFrame
     * @param callable $callback
     * @return Iterator
     * @throws DataFlowException
     */
    public function call(?Iterator $dataFrame, callable $callback): Iterator
    {
        if ($dataFrame !== null) {

            if ($callback instanceof Closure) {
                $callback = Closure::bind($callback, $this, get_called_class());
            }

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
                    throw new DataFlowException($exception);
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
