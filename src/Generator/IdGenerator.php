<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Generator;

use Roslov\QueueBundle\Tests\Generator\IdGeneratorTest;

use function md5;
use function uniqid;

/**
 * Generates request ids for RPC requests.
 *
 * @see IdGeneratorTest
 */
final class IdGenerator implements IdGeneratorInterface
{
    /**
     * @inheritDoc
     */
    public function generateId(): string
    {
        return md5(uniqid('', true));
    }
}
