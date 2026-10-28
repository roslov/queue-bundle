<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Psr\Container\ContainerInterface;
use Roslov\QueueBundle\Tests\Producer\ProducerLocatorTest;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * RabbitMQ producer locator — gets RabbitMQ producer without using the service container.
 *
 * @see ProducerLocatorTest
 */
final readonly class ProducerLocator implements ServiceSubscriberInterface
{
    /**
     * Constructor.
     *
     * @param ContainerInterface $locator Service container
     */
    public function __construct(private ContainerInterface $locator)
    {
    }

    /**
     * Returns the producer by name.
     *
     * @param string $name Producer name
     *
     * @return BaseProducer Producer
     */
    public function get(string $name): BaseProducer
    {
        $producerServiceName = "old_sound_rabbit_mq.{$name}_producer";

        /** @phpstan-var BaseProducer */
        return $this->locator->get($producerServiceName);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedServices(): array
    {
        return [
            '?' . BaseProducer::class,
        ];
    }
}
