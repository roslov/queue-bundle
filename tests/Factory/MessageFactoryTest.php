<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Exception\InvalidPayloadClassException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use stdClass;

/**
 * Tests MessageFactory.
 */
final class MessageFactoryTest extends TestCase
{
    /**
     * Tests that createMessage constructs a Message with expected fields.
     */
    public function testCreateMessageSuccess(): void
    {
        $payloadFactory = new PayloadFactory(['Dummy' => DummyDto::class]);
        $messageFactory = new MessageFactory($payloadFactory, 'my_service');

        $dto = new DummyDto();
        $message = $messageFactory->createMessage($dto);

        $this->assertSame('Dummy', $message->getType());
        $this->assertSame('my_service', $message->getSource());
        $this->assertSame('not-implemented', $message->getCorrelationId());
        $this->assertSame($dto, $message->getData());
    }

    /**
     * Tests that createMessage throws an exception when the payload is unsupported.
     */
    public function testCreateMessageWithUnsupportedPayload(): void
    {
        $payloadFactory = new PayloadFactory([]);
        $messageFactory = new MessageFactory($payloadFactory, 'my_service');

        $this->expectException(InvalidPayloadClassException::class);

        $messageFactory->createMessage(new stdClass());
    }
}
