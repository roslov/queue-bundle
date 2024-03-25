<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

/**
 * Queue DTO: Message
 */
final class Message implements MessageInterface
{
    /**
     * @var string Message type
     */
    private string $type;

    /**
     * @var string Source
     */
    private string $source;

    /**
     * @var string Correlation id
     */
    private string $correlationId;

    /**
     * @var object Payload
     */
    private object $data;

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @inheritDoc
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * @inheritDoc
     */
    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * @inheritDoc
     */
    public function setCorrelationId(string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    /**
     * @inheritDoc
     */
    public function getData(): object
    {
        return $this->data;
    }

    /**
     * @inheritDoc
     */
    public function setData(object $data): void
    {
        $this->data = $data;
    }
}
