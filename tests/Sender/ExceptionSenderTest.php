<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Sender;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Sender\ExceptionSender;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;
use RuntimeException;

use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Tests ExceptionSender.
 */
final class ExceptionSenderTest extends TestCase
{
    private ?string $originalUri = null;

    /**
     * Tests sending exception event with REQUEST_URI present.
     */
    public function testSendExceptionThrownEventWithUri(): void
    {
		// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $_SERVER['REQUEST_URI'] = '/api/v1/orders?filter=active';
		// phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $producer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (string $json): bool {
                /**
                 * @var array{
                 *     type: string,
                 *     source: string,
                 *     data: array{
                 *         serviceName: string,
                 *         exceptionClass: string,
                 *         message: string,
                 *         uri: string|null
                 *     }
                 * } $envelope
                 */
                $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return $envelope['type'] === 'Exception.Thrown'
                    && $envelope['source'] === 'test_service'
                    && $envelope['data']['serviceName'] === 'test_service'
                    && $envelope['data']['exceptionClass'] === RuntimeException::class
                    && $envelope['data']['message'] === 'Something failed'
                    && $envelope['data']['uri'] === '/api/v1/orders?filter=active';
            }));

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
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

        $producerFacade = new ProducerFacade($processor);
        $sender = new ExceptionSender($producerFacade, new ExceptionShortener(), 'test_service');

        $sender->sendExceptionThrownEvent(new RuntimeException('Something failed'));
    }

    /**
     * Tests sending exception event without REQUEST_URI.
     */
    public function testSendExceptionThrownEventWithoutUri(): void
    {
		// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        unset($_SERVER['REQUEST_URI']);
		// phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable

        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $producer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (string $json): bool {
                /**
                 * @var array{
                 *     type: string,
                 *     data: array{
                 *         uri: string|null
                 *     }
                 * } $envelope
                 */
                $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return $envelope['type'] === 'Exception.Thrown'
                    && $envelope['data']['uri'] === null;
            }));

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
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

        $producerFacade = new ProducerFacade($processor);
        $sender = new ExceptionSender($producerFacade, new ExceptionShortener(), 'test_service');

        $sender->sendExceptionThrownEvent(new RuntimeException('CLI error'));
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $this->originalUri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI'])
            ? $_SERVER['REQUEST_URI']
            : null;
        // phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        if ($this->originalUri !== null) {
            $_SERVER['REQUEST_URI'] = $this->originalUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }

        // phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
    }
}
