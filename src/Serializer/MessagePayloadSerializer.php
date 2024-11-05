<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Serializer;

use Roslov\QueueBundle\Exception\DeserializationException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Throwable;

/**
 * Message payload serializer.
 *
 * Serializes the queue messages to JSON and back.
 *
 * This class returns only the payload of a message. At the same time, the serialized string contains additional
 * information like message type, source, correlation id.
 */
final class MessagePayloadSerializer
{
    /**
     * Maximum length of a string for an exception message
     */
    private const MAX_LENGTH = 1000;

    /**
     * @var PayloadFactory Payload factory
     */
    private PayloadFactory $payloadFactory;

    /**
     * @var MessageFactory Message factory
     */
    private MessageFactory $messageFactory;

    /**
     * Constructor.
     *
     * @param PayloadFactory $payloadFactory Payload factory
     * @param MessageFactory $messageFactory Message factory
     */
    public function __construct(PayloadFactory $payloadFactory, MessageFactory $messageFactory)
    {
        $this->payloadFactory = $payloadFactory;
        $this->messageFactory = $messageFactory;
    }

    /**
     * Serializes the message payload to JSON.
     *
     * The resulting string will contain the message data like message type, source, etc.
     *
     * @param object $payload Message Payload
     * @return string Serialized message
     */
    public function serialize(object $payload): string
    {
        $message = $this->messageFactory->createMessage($payload);
        return $this->getSerializer()->serialize($message, 'json');
    }

    /**
     * Deserializes the JSON string to the message object with payload.
     *
     * The input JSON should contain the message data like message type, source, etc.
     *
     * @param string $json Serialized JSON string
     * @return object Message payload
     *
     * @throws DeserializationException If string cannot be deserialized
     */
    public function deserialize(string $json): object
    {
        try {
            $message = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $payloadClass = $this->payloadFactory->getPayloadClassName($message['type']);
            $payload = $this->getSerializer()->deserialize(
                json_encode($message['data'], JSON_THROW_ON_ERROR),
                $payloadClass,
                'json'
            );
        } catch (Throwable $e) {
            throw new DeserializationException('Deserialization failed for JSON: ' . $this->cutString($json), 0, $e);
        }
        return $payload;
    }

    /**
     * Returns the serializer.
     *
     * @return SerializerInterface Serializer
     */
    private function getSerializer(): SerializerInterface
    {
        return new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /**
     * Shortens the string and adds `...` at the end.
     *
     * @param string $string String
     * @return string Processed string
     */
    private function cutString(string $string): string
    {
        return mb_strlen($string) > self::MAX_LENGTH
            ? mb_strcut($string, 0, self::MAX_LENGTH - 3) . '...'
            : $string;
    }
}
