<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * RabbitMQ producer locator — gets RabbitMQ producer without using the service container.
 */
final class ProducerLocator implements ServiceSubscriberInterface
{
    /**
     * @var ContainerInterface Service container
     */
    private ContainerInterface $locator;

    /**
     * Constructor.
     *
     * @param ContainerInterface $locator Service container
     */
    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Returns the producer by name.
     *
     * @param string $name Producer name
     * @return BaseProducer Producer
     */
    public function get(string $name): BaseProducer
    {
        $producerServiceName = "old_sound_rabbit_mq.{$name}_producer";
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
