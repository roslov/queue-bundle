<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Factory;

use Roslov\QueueBundle\Dto\Message;
use Roslov\QueueBundle\Dto\MessageInterface;

/**
 * Message factory.
 *
 * It creates a new message with default values.
 *
 * @todo Add correlationId implementation.
 */
final readonly class MessageFactory
{
    /**
     * Constructor.
     *
     * @param PayloadFactory $payloadFactory Payload factory
     * @param string $source Message source
     */
    public function __construct(private PayloadFactory $payloadFactory, private string $source)
    {
    }

    /**
     * Creates a new message.
     *
     * It fills the default fields (including the correlation id), adds the message type and payload.
     *
     * @param object $data Payload
     *
     * @return MessageInterface Message
     */
    public function createMessage(object $data): MessageInterface
    {
        $message = new Message();
        $message->setType($this->payloadFactory->getMessageType($data));
        $message->setSource($this->source);
        $message->setCorrelationId('not-implemented');
        $message->setData($data);

        return $message;
    }
}
