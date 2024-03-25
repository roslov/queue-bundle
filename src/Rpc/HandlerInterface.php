<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Rpc;

/**
 * Interface: RPC command handler.
 */
interface HandlerInterface
{
    /**
     * Processes the command and returns the result.
     *
     * @param object $command Command
     * @return object Result of the command execution
     */
    public function handle(object $command): object;
}
