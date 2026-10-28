<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Subscriber\EventSenderSubscriber;

/**
 * Tests EventSenderSubscriber.
 */
final class EventSenderSubscriberTest extends TestCase
{
    /**
     * Tests that sendEvents executes sendAll without dry-run when subscriber is enabled.
     */
    public function testSendEventsWhenEnabled(): void
    {
        $payloadFactory = new PayloadFactory([]);
        $messageFactory = new MessageFactory($payloadFactory, 'test');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([], ['microtime' => 'ASC'])
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->once())->method('getRepository')->with(Event::class)->willReturn($repository);

        $locator = new ProducerLocator($this->createStub(ContainerInterface::class));
        $processor = new EventProcessor(
            true,
            false,
            $locator,
            $em,
            new NullLogger(),
            $serializer,
            new LogObfuscator(),
        );

        $subscriber = new EventSenderSubscriber($processor, true);
        $subscriber->sendEvents();
    }

    /**
     * Tests that sendEvents executes sendAll with dry-run when subscriber is disabled.
     */
    public function testSendEventsWhenDisabled(): void
    {
        $payloadFactory = new PayloadFactory([]);
        $messageFactory = new MessageFactory($payloadFactory, 'test');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->never())->method('getRepository');

        $locator = new ProducerLocator($this->createStub(ContainerInterface::class));
        $processor = new EventProcessor(
            true,
            false,
            $locator,
            $em,
            new NullLogger(),
            $serializer,
            new LogObfuscator(),
        );

        $subscriber = new EventSenderSubscriber($processor, false);
        $subscriber->sendEvents();
    }
}
