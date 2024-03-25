<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Generator;

/**
 * Generates request ids for RPC requests.
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
