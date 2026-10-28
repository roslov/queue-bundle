<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Rpc\HandlerInterface;

/**
 * Fixture: Dummy RPC handler.
 */
final class DummyHandler implements HandlerInterface
{
    /**
     * @inheritDoc
     */
    public function handle(object $command): object
    {
        return $command;
    }
}
