<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall

namespace Roslov\QueueBundle\Factory;

use Roslov\QueueBundle\Exception\InvalidMessageTypeException;
use Roslov\QueueBundle\Exception\InvalidPayloadClassException;

use function array_search;
use function sprintf;

/**
 * Message payload factory.
 *
 * It creates the message payload DTOs based on the message type.
 */
final class PayloadFactory
{
    /**
     * Constructor.
     *
     * @param array<string, string> $payloadMapping Type-to-payload mapping
     */
    public function __construct(private array $payloadMapping)
    {
    }

    /**
     * Creates the payload.
     *
     * @param string $messageType Message type
     *
     * @return object Payload
     *
     * @throws InvalidMessageTypeException If the message type is not supported
     */
    public function createPayload(string $messageType): object
    {
        $class = $this->getPayloadClassName($messageType);

        return new $class();
    }

    /**
     * Returns the payload class name.
     *
     * @param string $messageType Message type
     *
     * @return string Payload class name
     *
     * @throws InvalidMessageTypeException If the message type is not supported
     */
    public function getPayloadClassName(string $messageType): string
    {
        $class = $this->payloadMapping[$messageType] ?? null;
        if ($class === null) {
            throw new InvalidMessageTypeException(sprintf('Message type "%s" is not supported.', $messageType));
        }

        return $class;
    }

    /**
     * Returns the message type.
     *
     * @param object $payload Payload
     *
     * @return string Payload class name
     *
     * @throws InvalidPayloadClassException If the payload class is not supported
     */
    public function getMessageType(object $payload): string
    {
        $class = $payload::class;
        $type = array_search($class, $this->payloadMapping, true);
        if ($type === false) {
            throw new InvalidPayloadClassException(sprintf('Payload class "%s" is not supported.', $class));
        }

        return $type;
    }
}
