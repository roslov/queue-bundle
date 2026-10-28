<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use OldSound\RabbitMqBundle\RabbitMq\Producer;
use Roslov\QueueBundle\Tests\Producer\BaseProducerTest;

/**
 * Base producer.
 *
 * All producers should extend this class and set a routing key.
 *
 * @see BaseProducerTest
 */
abstract class BaseProducer extends Producer implements ProducerInterface
{
    /**
     * Returns the routing key.
     *
     * @return string Routing key
     */
    abstract protected function getRoutingKey(): string;

    /**
     * @inheritDoc
     */
    public function send(string $body): void
    {
        $this->setContentType('application/json');
        $this->publish($body, $this->getRoutingKey());
    }
}
