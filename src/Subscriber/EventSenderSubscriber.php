<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Subscriber;

use OldSound\RabbitMqBundle\Event\AMQPEvent;
use Roslov\QueueBundle\Processor\EventProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends all previously stored events to the queue on the kernel termination.
 */
final class EventSenderSubscriber implements EventSubscriberInterface
{
    /**
     * @var EventProcessor Event processor
     */
    private EventProcessor $eventProcessor;

    /**
     * @var bool Whether event processor is enabled
     */
    private bool $enabled;

    /**
     * Constructor.
     *
     * @param EventProcessor $eventProcessor Event processor
     * @param bool $enabled Whether event processor is enabled
     */
    public function __construct(EventProcessor $eventProcessor, bool $enabled)
    {
        $this->eventProcessor = $eventProcessor;
        $this->enabled = $enabled;
    }

    /**
     * Sends all previously stored events to the queue.
     */
    public function sendEvents(): void
    {
        $this->eventProcessor->sendAll(!$this->enabled);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'sendEvents',
            AMQPEvent::ON_CONSUME => 'sendEvents',
            AMQPEvent::AFTER_PROCESSING_MESSAGE => 'sendEvents',
        ];
    }
}
