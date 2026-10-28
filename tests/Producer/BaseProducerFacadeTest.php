<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Producer;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducerFacade;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Tests BaseProducerFacade.
 */
final class BaseProducerFacadeTest extends TestCase
{
    /**
     * Tests that send invokes EventProcessor to save and send the payload.
     */
    public function testSend(): void
    {
        $payloadFactory = new PayloadFactory(['Dummy' => DummyDto::class]);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['send'])
            ->getMock();

        $producer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (string $json): bool {
                /** @var array{type: string, source: string} $data */
                $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

                return $data['type'] === 'Dummy' && $data['source'] === 'test_service';
            }));

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('old_sound_rabbit_mq.dummy_producer')
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

        $facade = new DummyProducerFacade($processor);
        $facade->sendEvent('dummy', new DummyDto());
    }
}
