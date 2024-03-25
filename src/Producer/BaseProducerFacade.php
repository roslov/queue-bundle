<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Roslov\QueueBundle\Processor\EventProcessor;

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
 */
abstract class BaseProducerFacade
{
    /**
     * @var EventProcessor Event processor
     */
    private EventProcessor $eventProcessor;

    /**
     * Constructor.
     *
     * @param EventProcessor $eventProcessor Event processor
     */
    public function __construct(EventProcessor $eventProcessor)
    {
        $this->eventProcessor = $eventProcessor;
    }

    /**
     * Sends the message via selected producer.
     *
     * @param string $producerName Short producer name
     * @param object $payload Message payload
     */
    protected function send(string $producerName, object $payload): void
    {
        $this->eventProcessor->save($producerName, $payload);
    }
}
