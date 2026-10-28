<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Producer\BaseProducer;

/**
 * Fixture: Dummy producer.
 */
abstract class DummyProducer extends BaseProducer
{
    /**
     * @inheritDoc
     */
    protected function getRoutingKey(): string
    {
        return 'dummy-routing-key';
    }
}
