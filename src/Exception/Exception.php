<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Exception;

use RuntimeException;

/**
 * Base exception.
 *
 * Other runtime exceptions should be extended from this one.
 */
abstract class Exception extends RuntimeException
{
}
