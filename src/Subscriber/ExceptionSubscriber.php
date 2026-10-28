<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Subscriber;

use InvalidArgumentException;
use Roslov\QueueBundle\Sender\ExceptionSender;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

use function is_callable;
use function sprintf;

/**
 * Sends an event automatically on exception.
 */
final readonly class ExceptionSubscriber
{
    private const int HTTP_STATUS_CODE_INTERNAL_SERVER_ERROR = 500;

    /**
     * @param bool $enabled Whether the subscriber is enabled
     * @param object|null $exceptionValidator Exception validator
     * @param ExceptionSender $exceptionSender Exception sender
     */
    public function __construct(
        private bool $enabled,
        private ?object $exceptionValidator,
        private ExceptionSender $exceptionSender,
    ) {
    }

    /**
     * @param object $event Event
     */
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
    public function notifyException(object $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $exception = $this->getException($event);

        if (is_callable($this->exceptionValidator) && !($this->exceptionValidator)($exception)) {
            return;
        }

        if (
            $exception instanceof HttpException
            && self::HTTP_STATUS_CODE_INTERNAL_SERVER_ERROR !== $exception->getStatusCode()
        ) {
            return;
        }

        $this->exceptionSender->sendExceptionThrownEvent($exception);
    }

    /**
     * @param ConsoleErrorEvent $event Event
     */
    #[AsEventListener(event: ConsoleEvents::ERROR, priority: 0)]
    public function notifyConsoleException(ConsoleErrorEvent $event): void
    {
        if (!$this->enabled) {
            return;
        }

        if (is_callable($this->exceptionValidator) && !($this->exceptionValidator)($event->getError())) {
            return;
        }

        $this->exceptionSender->sendExceptionThrownEvent($event->getError());
    }

    /**
     * Gets the exception from different possible event instances.
     *
     * @param object $event Event
     *
     * @return Throwable Exception
     */
    private function getException(object $event): Throwable
    {
        if ($event instanceof ExceptionEvent) {
            return $event->getThrowable();
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid event object. Allowed: %s or %s',
            ExceptionEvent::class,
            ExceptionEvent::class,
        ));
    }
}
