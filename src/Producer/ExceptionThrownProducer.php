<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Roslov\QueueBundle\Tests\Producer\ExceptionThrownProducerTest;

/**
 * Producer: Sends information that the exception was thrown.
 *
 * @see ExceptionThrownProducerTest
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
