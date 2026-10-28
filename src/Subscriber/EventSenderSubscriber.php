<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Subscriber;

use OldSound\RabbitMqBundle\Event\AMQPEvent;
use Roslov\QueueBundle\Processor\EventProcessor;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends all previously stored events to the queue on the kernel termination.
 */
final readonly class EventSenderSubscriber
{
    /**
     * Constructor.
     *
     * @param EventProcessor $eventProcessor Event processor
     * @param bool $enabled Whether event processor is enabled
     */
    public function __construct(private EventProcessor $eventProcessor, private bool $enabled)
    {
    }

    /**
     * Sends all previously stored events to the queue.
     */
    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: AMQPEvent::ON_CONSUME)]
    #[AsEventListener(event: AMQPEvent::AFTER_PROCESSING_MESSAGE)]
    public function sendEvents(): void
    {
        $this->eventProcessor->sendAll(!$this->enabled);
    }
}
