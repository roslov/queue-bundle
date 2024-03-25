<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

/**
 * Producer: Sends information that the exception was thrown.
 */
final class ExceptionThrownProducer extends BaseProducer
{
    /**
     * @inheritDoc
     */
    protected function getRoutingKey(): string
    {
        return 'exception-thrown';
    }
}
