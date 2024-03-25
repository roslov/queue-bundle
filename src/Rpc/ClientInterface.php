<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Rpc;

use Roslov\QueueBundle\Exception\RequestIdNotFoundRpcClientException;
use Roslov\QueueBundle\Exception\TimeoutRpcClientException;

/**
 * Interface: RPC client.
 */
interface ClientInterface
{
    /**
     * Request expiration time (in seconds)
     */
    public const TIMEOUT = 15;

    /**
     * Makes remote procedure call.
     *
     * @param object $command Command DTO
     * @param string $exchangeName Exchange name
     * @param int $timeout Request expiration time (in seconds)
     * @return object Response
     *
     * @throws TimeoutRpcClientException On timeout
     * @throws RequestIdNotFoundRpcClientException If the requested reply was not received
     */
    public function call(object $command, string $exchangeName, int $timeout = self::TIMEOUT): object;
}
