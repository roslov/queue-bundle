<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Factory;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Exception\InvalidMessageTypeException;
use Roslov\QueueBundle\Exception\InvalidPayloadClassException;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use stdClass;

/**
 * Tests PayloadFactory.
 */
final class PayloadFactoryTest extends TestCase
{
    /**
     * Tests that createPayload creates an instance of mapped DTO.
     */
    public function testCreatePayloadSuccess(): void
    {
        $factory = new PayloadFactory(['Dummy' => DummyDto::class]);
        $payload = $factory->createPayload('Dummy');

        $this->assertInstanceOf(DummyDto::class, $payload);
    }

    /**
     * Tests that createPayload throws an exception when the type is unknown.
     */
    public function testCreatePayloadThrowsExceptionOnUnknownType(): void
    {
        $factory = new PayloadFactory([]);

        $this->expectException(InvalidMessageTypeException::class);
        $this->expectExceptionMessage('Message type "Unknown" is not supported.');

        $factory->createPayload('Unknown');
    }

    /**
     * Tests that getPayloadClassName returns the class name for a known type.
     */
    public function testGetPayloadClassNameSuccess(): void
    {
        $factory = new PayloadFactory(['Dummy' => DummyDto::class]);

        $this->assertSame(DummyDto::class, $factory->getPayloadClassName('Dummy'));
    }

    /**
     * Tests that getPayloadClassName throws an exception for an unknown type.
     */
    public function testGetPayloadClassNameThrowsExceptionOnUnknownType(): void
    {
        $factory = new PayloadFactory([]);

        $this->expectException(InvalidMessageTypeException::class);
        $this->expectExceptionMessage('Message type "NonExistent" is not supported.');

        $factory->getPayloadClassName('NonExistent');
    }

    /**
     * Tests that getMessageType returns the message type for a known payload.
     */
    public function testGetMessageTypeSuccess(): void
    {
        $factory = new PayloadFactory(['Dummy' => DummyDto::class]);

        $this->assertSame('Dummy', $factory->getMessageType(new DummyDto()));
    }

    /**
     * Tests that getMessageType throws an exception for an unknown payload class.
     */
    public function testGetMessageTypeThrowsExceptionOnUnknownClass(): void
    {
        $factory = new PayloadFactory([]);

        $this->expectException(InvalidPayloadClassException::class);
        $this->expectExceptionMessage('Payload class "stdClass" is not supported.');

        $factory->getMessageType(new stdClass());
    }
}
