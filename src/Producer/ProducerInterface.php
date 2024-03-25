<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

/**
 * Interface: Producer
 */
interface ProducerInterface
{
    /**
     * Sends the message to the queue.
     *
     * @param string $body Message body in JSON
     */
    public function send(string $body): void;
}
