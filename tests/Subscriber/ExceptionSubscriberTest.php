<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Subscriber;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\BaseProducer;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Sender\ExceptionSender;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Subscriber\ExceptionSubscriber;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Throwable;

/**
 * Tests ExceptionSubscriber.
 */
final class ExceptionSubscriberTest extends TestCase
{
    /**
     * Tests that notifyException does nothing when subscriber is disabled.
     */
    public function testNotifyExceptionWhenDisabledDoesNothing(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(false, null, $sender);

        $event = $this->createExceptionEvent(new RuntimeException('Boom'));
        $subscriber->notifyException($event);
    }

    /**
     * Tests that notifyException does nothing when exceptionValidator returns false.
     */
    public function testNotifyExceptionWhenValidatorReturnsFalse(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $validator = static fn (Throwable $e): bool => $e->getMessage() === 'allowed';
        $subscriber = new ExceptionSubscriber(true, $validator, $sender);

        $event = $this->createExceptionEvent(new RuntimeException('Boom'));
        $subscriber->notifyException($event);
    }

    /**
     * Tests that notifyException skips HttpException with status code other than 500.
     */
    public function testNotifyExceptionSkipsNon500HttpException(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(true, null, $sender);

        $event = $this->createExceptionEvent(new NotFoundHttpException('Not Found'));
        $subscriber->notifyException($event);
    }

    /**
     * Tests that notifyException sends event for HttpException with status code 500.
     */
    public function testNotifyExceptionSends500HttpException(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->once())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(true, null, $sender);

        $event = $this->createExceptionEvent(new HttpException(500, 'Server Error'));
        $subscriber->notifyException($event);
    }

    /**
     * Tests that notifyException sends event for non-HTTP Throwable.
     */
    public function testNotifyExceptionSendsGenericThrowable(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->once())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(true, null, $sender);

        $event = $this->createExceptionEvent(new RuntimeException('Crash'));
        $subscriber->notifyException($event);
    }

    /**
     * Tests that notifyException throws InvalidArgumentException when given an invalid event object.
     */
    public function testNotifyExceptionThrowsOnInvalidEvent(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(true, null, $sender);

        $this->expectException(InvalidArgumentException::class);
        $subscriber->notifyException(new stdClass());
    }

    /**
     * Tests that notifyConsoleException does nothing when subscriber is disabled.
     */
    public function testNotifyConsoleExceptionWhenDisabledDoesNothing(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(false, null, $sender);

        $event = new ConsoleErrorEvent(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
            new RuntimeException('Console error'),
        );
        $subscriber->notifyConsoleException($event);
    }

    /**
     * Tests that notifyConsoleException does nothing when exceptionValidator returns false.
     */
    public function testNotifyConsoleExceptionWhenValidatorReturnsFalse(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->never())->method('send');

        $sender = $this->createExceptionSender($producer);
        $validator = static fn (Throwable $e): bool => $e->getMessage() === 'allowed';
        $subscriber = new ExceptionSubscriber(true, $validator, $sender);

        $event = new ConsoleErrorEvent(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
            new RuntimeException('Console error'),
        );
        $subscriber->notifyConsoleException($event);
    }

    /**
     * Tests that notifyConsoleException sends event when enabled.
     */
    public function testNotifyConsoleExceptionSendsEvent(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();
        $producer->expects($this->once())->method('send');

        $sender = $this->createExceptionSender($producer);
        $subscriber = new ExceptionSubscriber(true, null, $sender);

        $event = new ConsoleErrorEvent(
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
            new RuntimeException('Command failed'),
        );
        $subscriber->notifyConsoleException($event);
    }

    /**
     * Creates an ExceptionSender wired to a mock producer.
     *
     * @param BaseProducer $producer Mock producer
     *
     * @return ExceptionSender Configured exception sender
     */
    private function createExceptionSender(BaseProducer $producer): ExceptionSender
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->method('get')
            ->with('old_sound_rabbit_mq.event_exception_thrown_producer')
            ->willReturn($producer);

        $processor = new EventProcessor(
            true,
            true,
            new ProducerLocator($container),
            null,
            new NullLogger(),
            $serializer,
            new LogObfuscator(),
        );

        return new ExceptionSender(new ProducerFacade($processor), new ExceptionShortener(), 'test_service');
    }

    /**
     * Creates a dummy ExceptionEvent.
     *
     * @param Throwable $throwable Exception to wrap
     *
     * @return ExceptionEvent Exception event
     */
    private function createExceptionEvent(Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );
    }
}
