<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Fixture: Dummy RPC command DTO.
 */
#[AsQueuePayload('DummyCommand', rpcHandler: DummyHandler::class)]
final class DummyCommandDto
{
}
