<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Tests the queue payload attribute.
 */
final class AsQueuePayloadTest extends TestCase
{
    /**
     * Tests attribute creation with a payload type only.
     */
    public function testPayloadTypeOnly(): void
    {
        $attribute = new AsQueuePayload('UserCreated');

        $this->assertSame('UserCreated', $attribute->getType());
        $this->assertNull($attribute->getRpcHandler());
    }

    /**
     * Tests attribute creation with payload type and RPC handler.
     */
    public function testPayloadTypeAndRpcHandler(): void
    {
        $attribute = new AsQueuePayload('GetUserCommand', 'App\Rpc\UserHandler');

        $this->assertSame('GetUserCommand', $attribute->getType());
        $this->assertSame('App\Rpc\UserHandler', $attribute->getRpcHandler());
    }
}
