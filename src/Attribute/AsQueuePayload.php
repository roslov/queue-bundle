<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Attribute;

use Attribute;
use Roslov\QueueBundle\Tests\Attribute\AsQueuePayloadTest;

/**
 * Queue payload attribute.
 *
 * Used to configure DTO mapping and RPC handler directly on the DTO class.
 *
 * @see AsQueuePayloadTest
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsQueuePayload
{
    /**
     * Constructor.
     *
     * @param string $type Message type
     * @param string|null $rpcHandler RPC handler service or class name
     */
    public function __construct(private string $type, private ?string $rpcHandler = null)
    {
    }

    /**
     * Returns the message type.
     *
     * @return string Message type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Returns the RPC handler service or class name.
     *
     * @return string|null RPC handler
     */
    public function getRpcHandler(): ?string
    {
        return $this->rpcHandler;
    }
}
