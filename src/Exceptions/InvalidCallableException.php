<?php

namespace Simsoft\DataFlow\Exceptions;

use InvalidArgumentException;

/**
 * InvalidCallableException class.
 *
 * Thrown when a non-callable value is passed where a callable is expected.
 */
class InvalidCallableException extends InvalidArgumentException
{
}
