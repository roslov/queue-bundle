<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Attribute;

use Attribute;

/**
 * Queue payload attribute.
 *
 * Used to configure DTO mapping and RPC handler directly on the DTO class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class AsQueuePayload
{
    /**
     * @var string Message type
     */
    private string $type;

    /**
     * @var string|null RPC handler service or class name
     */
    private ?string $rpcHandler;

    /**
     * Constructor.
     *
     * @param string $type Message type
     * @param string|null $rpcHandler RPC handler service or class name
     */
    public function __construct(string $type, ?string $rpcHandler = null)
    {
        $this->type = $type;
        $this->rpcHandler = $rpcHandler;
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
