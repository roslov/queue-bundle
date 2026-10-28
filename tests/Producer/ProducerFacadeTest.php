<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Producer;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Tests ProducerFacade.
 */
final class ProducerFacadeTest extends TestCase
{
    /**
     * Tests that sendExceptionThrownEvent constructs ExceptionThrown DTO and sends it immediately.
     */
    public function testSendExceptionThrownEvent(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'alert_service');
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
                 *         hostName: string|null,
                 *         exceptionClass: string,
                 *         file: string,
                 *         line: int,
                 *         message: string,
                 *         trace: string,
                 *         uri: string|null
                 *     }
                 * } $envelope
                 */
                $envelope = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return $envelope['type'] === 'Exception.Thrown'
                    && $envelope['source'] === 'alert_service'
                    && $envelope['data']['serviceName'] === 'web_app'
                    && $envelope['data']['hostName'] === 'web-1'
                    && $envelope['data']['exceptionClass'] === 'RuntimeException'
                    && $envelope['data']['file'] === '/app/index.php'
                    && $envelope['data']['line'] === 12
                    && $envelope['data']['message'] === 'Error message'
                    && $envelope['data']['trace'] === '#0 trace'
                    && $envelope['data']['uri'] === '/api/test';
            }));

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('old_sound_rabbit_mq.event_exception_thrown_producer')
            ->willReturn($producer);

        $producerLocator = new ProducerLocator($container);
        $processor = new EventProcessor(
            true,
            true,
            $producerLocator,
            null,
            new NullLogger(),
            $serializer,
            new LogObfuscator(),
        );

        $facade = new ProducerFacade($processor);
        $facade->sendExceptionThrownEvent(
            'web_app',
            'web-1',
            'RuntimeException',
            '/app/index.php',
            12,
            'Error message',
            '#0 trace',
            '/api/test',
        );
    }
}
