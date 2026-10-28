<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Tests\Producer\BaseProducerFacadeTest;

/**
 * Keeps all calls to producers.
 *
 * Example:
 * ```php
 * public function sendUserChangedEvent(int $userId): void
 * {
 *     $payload = new \App\Dto\UserChanged();
 *     $payload->setId($userId);
 *     $this->send('user_changed', $payload);
 * }
 * ```
 *
 * @see BaseProducerFacadeTest
 */
abstract class BaseProducerFacade
{
    /**
     * Constructor.
     *
     * @param EventProcessor $eventProcessor Event processor
     */
    public function __construct(private readonly EventProcessor $eventProcessor)
    {
    }

    /**
     * Sends the message via a selected producer.
     *
     * @param string $producerName Short producer name
     * @param object $payload Message payload
     * @param bool $sendImmediately Whether to send the message immediately.
     * This can be useful for sending exception alerts or when the queue storage fails
     */
    protected function send(string $producerName, object $payload, bool $sendImmediately = false): void
    {
        $this->eventProcessor->save($producerName, $payload, $sendImmediately);
    }
}
