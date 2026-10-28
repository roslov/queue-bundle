<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Producer;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Roslov\QueueBundle\Producer\BaseProducer;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;

/**
 * Tests ProducerLocator.
 */
final class ProducerLocatorTest extends TestCase
{
    /**
     * Tests that get resolves producer by prepending old_sound_rabbit_mq and appending _producer.
     */
    public function testGetProducer(): void
    {
        $dummyProducer = $this->createStub(DummyProducer::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('old_sound_rabbit_mq.user_created_producer')
            ->willReturn($dummyProducer);

        $locator = new ProducerLocator($container);

        $this->assertSame($dummyProducer, $locator->get('user_created'));
    }

    /**
     * Tests that getSubscribedServices returns BaseProducer subscription.
     */
    public function testGetSubscribedServices(): void
    {
        $services = ProducerLocator::getSubscribedServices();

        $this->assertSame(['?' . BaseProducer::class], $services);
    }
}
