<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Iterator;
use OldSound\RabbitMqBundle\Event\AfterProcessingMessageEvent;
use OldSound\RabbitMqBundle\Event\OnConsumeEvent;
use OldSound\RabbitMqBundle\RabbitMq\Consumer;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Subscriber\EventSenderSubscriber;
use stdClass;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelEvents;

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
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([], ['microtime' => 'ASC'])
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->once())->method('getRepository')->with(Event::class)->willReturn($repository);

        $processor = $this->createProcessor($em);

        $subscriber = new EventSenderSubscriber($processor, true);
        $subscriber->sendEvents();
    }

    /**
     * Tests that sendEvents executes sendAll with dry-run when subscriber is disabled.
     */
    public function testSendEventsWhenDisabled(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->never())->method('getRepository');

        $processor = $this->createProcessor($em);

        $subscriber = new EventSenderSubscriber($processor, false);
        $subscriber->sendEvents();
    }

    /**
     * Tests that event listener attributes are registered on sendEvents with expected consumer and kernel event names.
     */
    public function testEventListenerAttributes(): void
    {
        $method = new ReflectionMethod(EventSenderSubscriber::class, 'sendEvents');
        $attributes = $method->getAttributes(AsEventListener::class);

        $this->assertCount(3, $attributes);

        $eventNames = [];
        foreach ($attributes as $attribute) {
            $listener = $attribute->newInstance();
            $eventNames[] = $listener->event;
            $this->assertSame(0, $listener->priority);
        }

        $this->assertSame('on_consume', OnConsumeEvent::NAME);
        $this->assertSame('after_processing', AfterProcessingMessageEvent::NAME);

        $this->assertContains(KernelEvents::TERMINATE, $eventNames);
        $this->assertContains(OnConsumeEvent::NAME, $eventNames);
        $this->assertContains(AfterProcessingMessageEvent::NAME, $eventNames);
    }

    /**
     * Tests that dispatching registered events via an event dispatcher triggers outbox sending when enabled.
     *
     * @param string $eventName Event name
     */
    #[DataProvider('consumerEventsProvider')]
    public function testDispatchingConsumerEventsInvokesOutboxSending(string $eventName): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with([], ['microtime' => 'ASC'])
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->once())->method('getRepository')->with(Event::class)->willReturn($repository);

        $processor = $this->createProcessor($em);
        $subscriber = new EventSenderSubscriber($processor, true);

        $dispatcher = new EventDispatcher();
        $this->registerAttributedListeners($dispatcher, $subscriber);

        $dispatcher->dispatch($this->createEvent($eventName), $eventName);
    }

    /**
     * Tests that dispatching registered events via an event dispatcher triggers outbox dry-run when disabled.
     *
     * @param string $eventName Event name
     */
    #[DataProvider('consumerEventsProvider')]
    public function testDispatchingConsumerEventsWhenDisabledInvokesDryRun(string $eventName): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->never())->method('getRepository');

        $processor = $this->createProcessor($em);
        $subscriber = new EventSenderSubscriber($processor, false);

        $dispatcher = new EventDispatcher();
        $this->registerAttributedListeners($dispatcher, $subscriber);

        $dispatcher->dispatch($this->createEvent($eventName), $eventName);
    }

    /**
     * Returns event names handled by EventSenderSubscriber.
     *
     * @return Iterator<string, array{string}> Event names
     */
    public static function consumerEventsProvider(): Iterator
    {
        yield 'kernel.terminate' => [KernelEvents::TERMINATE];
        yield 'on_consume' => [OnConsumeEvent::NAME];
        yield 'after_processing' => [AfterProcessingMessageEvent::NAME];
    }

    /**
     * Creates an EventProcessor instance for testing.
     *
     * @param EntityManagerInterface $em Entity manager
     *
     * @return EventProcessor Event processor
     */
    private function createProcessor(EntityManagerInterface $em): EventProcessor
    {
        $payloadFactory = new PayloadFactory([]);
        $messageFactory = new MessageFactory($payloadFactory, 'test');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $locator = new ProducerLocator($this->createStub(ContainerInterface::class));

        return new EventProcessor(
            true,
            false,
            $locator,
            $em,
            new NullLogger(),
            $serializer,
            new LogObfuscator(),
        );
    }

    /**
     * Creates an event object for the given event name.
     *
     * @param string $eventName Event name
     *
     * @return object Event object
     */
    private function createEvent(string $eventName): object
    {
        if ($eventName === OnConsumeEvent::NAME) {
            return new OnConsumeEvent($this->createStub(Consumer::class));
        }

        if ($eventName === AfterProcessingMessageEvent::NAME) {
            return new AfterProcessingMessageEvent($this->createStub(Consumer::class), new AMQPMessage());
        }

        return new stdClass();
    }

    /**
     * Registers attributed event listeners on an event dispatcher.
     *
     * @param EventDispatcher $dispatcher Event dispatcher
     * @param object $subscriber Subscriber instance
     */
    private function registerAttributedListeners(EventDispatcher $dispatcher, object $subscriber): void
    {
        $refClass = new ReflectionClass($subscriber);
        foreach ($refClass->getMethods() as $method) {
            $this->registerMethodListeners($dispatcher, $subscriber, $method);
        }
    }

    /**
     * Registers attributed event listeners for a specific method on an event dispatcher.
     *
     * @param EventDispatcher $dispatcher Event dispatcher
     * @param object $subscriber Subscriber instance
     * @param ReflectionMethod $method Method reflection
     */
    private function registerMethodListeners(
        EventDispatcher $dispatcher,
        object $subscriber,
        ReflectionMethod $method,
    ): void {
        foreach ($method->getAttributes(AsEventListener::class) as $attribute) {
            $listener = $attribute->newInstance();
            if ($listener->event !== null) {
                $dispatcher->addListener($listener->event, [$subscriber, $method->getName()], $listener->priority);
            }
        }
    }
}
