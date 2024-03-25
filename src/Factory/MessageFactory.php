<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Factory;

use Roslov\QueueBundle\Dto\Message;
use Roslov\QueueBundle\Dto\MessageInterface;

/**
 * Message factory.
 *
 * It creates a new message with defaults.
 *
 * @todo Add correlationId implementation.
 */
final class MessageFactory
{
    /**
     * @var string Message source
     */
    private string $source;

    /**
     * @var PayloadFactory Payload factory
     */
    private PayloadFactory $payloadFactory;

    /**
     * Constructor.
     *
     * @param PayloadFactory $payloadFactory Payload factory
     * @param string $source Message source
     */
    public function __construct(PayloadFactory $payloadFactory, string $source)
    {
        $this->payloadFactory = $payloadFactory;
        $this->source = $source;
    }

    /**
     * Creates a new message.
     *
     * It fills the default fields (including the correlation id), add the message type and payload.
     *
     * @param object $data Payload
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
