<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

/**
 * Queue error DTO
 */
final class Error
{
    /**
     * @var string Payload type (message subtype)
     */
    private string $type;

    /**
     * @var string Error message
     */
    private string $message;

    /**
     * Returns the payload type.
     *
     * @return string Type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Sets the payload type.
     *
     * @param string $type Type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * Returns the error message.
     *
     * @return string Error message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Sets the error message.
     *
     * @param string $message Error message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
