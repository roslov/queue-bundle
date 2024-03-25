<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Generator;

/**
 * Generates request ids for RPC requests.
 */
interface IdGeneratorInterface
{
    /**
     * Generates the request id.
     *
     * @return string Request id
     */
    public function generateId(): string;
}
