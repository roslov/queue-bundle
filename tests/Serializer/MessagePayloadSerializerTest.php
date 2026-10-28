<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Dto\Error;
use Roslov\QueueBundle\Dto\Trigger;
use Roslov\QueueBundle\Exception\DeserializationException;
use Roslov\QueueBundle\Exception\InvalidPayloadClassException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use stdClass;

use function str_repeat;

/**
 * Tests MessagePayloadSerializer.
 */
final class MessagePayloadSerializerTest extends TestCase
{
    /**
     * Tests round-trip serialization and deserialization for field-less DTO.
     */
    public function testSerializeAndDeserializeFieldlessDto(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS + ['Dummy' => DummyDto::class]);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $json = $serializer->serialize(new Trigger());

        $this->assertSame(
            '{"type":"Trigger","source":"test_service","correlationId":"not-implemented","data":[]}',
            $json,
        );

        $dto = $serializer->deserialize($json);
        $this->assertInstanceOf(Trigger::class, $dto);
    }

    /**
     * Tests round-trip serialization and deserialization for DTO with fields.
     */
    public function testSerializeAndDeserializeDtoWithFields(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $error = new Error();
        $error->setType('TestError');
        $error->setMessage('Something went wrong');

        $json = $serializer->serialize($error);
        $this->assertJson($json);

        $deserialized = $serializer->deserialize($json);
        $this->assertInstanceOf(Error::class, $deserialized);
        $this->assertSame('TestError', $deserialized->getType());
        $this->assertSame('Something went wrong', $deserialized->getMessage());
    }

    /**
     * Tests serialize throws InvalidPayloadClassException when DTO class is not mapped.
     */
    public function testSerializeThrowsOnUnmappedPayloadClass(): void
    {
        $payloadFactory = new PayloadFactory([]);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $this->expectException(InvalidPayloadClassException::class);
        $serializer->serialize(new stdClass());
    }

    /**
     * Tests deserialize throws DeserializationException on invalid JSON.
     */
    public function testDeserializeThrowsOnInvalidJson(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessage('Deserialization failed for JSON: {invalid-json');

        $serializer->deserialize('{invalid-json');
    }

    /**
     * Tests deserialize throws DeserializationException when type is unknown.
     */
    public function testDeserializeThrowsOnUnknownType(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $json = '{"type":"UnknownType","source":"service","correlationId":"1","data":{}}';

        $this->expectException(DeserializationException::class);
        $this->expectExceptionMessage('Deserialization failed for JSON: ' . $json);

        $serializer->deserialize($json);
    }

    /**
     * Tests that cutString shortens exception JSON strings longer than 1000 characters.
     */
    public function testDeserializeTruncatesLongJsonInException(): void
    {
        $payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);

        $longJson = '{"type":"invalid", "extra":"' . str_repeat('x', 1200) . '"}';

        try {
            $serializer->deserialize($longJson);
            $this->fail('Expected DeserializationException was not thrown.');
        } catch (DeserializationException $e) {
            $this->assertStringEndsWith('...', $e->getMessage());
            $this->assertStringContainsString('Deserialization failed for JSON:', $e->getMessage());
        }
    }
}
