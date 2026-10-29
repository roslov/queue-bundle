<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Subscriber;

use OldSound\RabbitMqBundle\Event\AfterProcessingMessageEvent;
use OldSound\RabbitMqBundle\Event\OnConsumeEvent;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Tests\Subscriber\EventSenderSubscriberTest;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends all previously stored events to the queue when the application terminates or a consumer finishes.
 *
 * @see EventSenderSubscriberTest
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
    #[AsEventListener(event: OnConsumeEvent::NAME)]
    #[AsEventListener(event: AfterProcessingMessageEvent::NAME)]
    public function sendEvents(): void
    {
        $this->eventProcessor->sendAll(!$this->enabled);
    }
}
