<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

/**
 * Interface: Queue message DTO
 */
interface MessageInterface
{
    /**
     * Returns the message type.
     *
     * @return string Type
     */
    public function getType(): string;

    /**
     * Sets the message type.
     *
     * @param string $type Type
     */
    public function setType(string $type): void;

    /**
     * Returns the source that produced the message.
     *
     * @return string Source
     */
    public function getSource(): string;

    /**
     * Sets the message source that produced the message.
     *
     * @param string $source Source
     */
    public function setSource(string $source): void;

    /**
     * Returns the correlation id.
     *
     * This id is used to trace requests and responses.
     *
     * @return string Correlation id
     */
    public function getCorrelationId(): string;

    /**
     * Sets the correlation id.
     *
     * This id is used to trace requests and responses.
     *
     * @param string $correlationId Correlation id
     */
    public function setCorrelationId(string $correlationId): void;

    /**
     * Returns the payload.
     *
     * This payload contains all the data for a specific message type.
     *
     * @return object Payload
     */
    public function getData(): object;

    /**
     * Sets the correlation id.
     *
     * This payload contains all the data for a specific message type.
     *
     * @param object $data Payload
     */
    public function setData(object $data): void;
}
