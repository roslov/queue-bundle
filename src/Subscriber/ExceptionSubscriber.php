<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Subscriber;

use InvalidArgumentException;
use Roslov\QueueBundle\Sender\ExceptionSender;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Sends an event automatically on exception.
 */
final class ExceptionSubscriber implements EventSubscriberInterface
{
    private const HTTP_STATUS_CODE_INTERNAL_SERVER_ERROR = 500;

    /**
     * @var bool Whether exception subscriber is enabled
     */
    private bool $enabled;

    /**
     * @var object|null The callable object that validates if a notification about the given exception should be sent
     */
    private ?object $exceptionValidator;

    /**
     * @var ExceptionSender Exception sender
     */
    private ExceptionSender $exceptionSender;

    /**
     * @param bool $enabled Whether the subscriber is enabled
     * @param object|null $exceptionValidator Exception validator
     * @param ExceptionSender $exceptionSender Exception sender
     */
    public function __construct(
        bool $enabled,
        ?object $exceptionValidator,
        ExceptionSender $exceptionSender,
    ) {
        $this->enabled = $enabled;
        $this->exceptionValidator = $exceptionValidator;
        $this->exceptionSender = $exceptionSender;
    }

    /**
     * @param object $event Event
     */
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
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => [
                ['notifyException', 0],
            ],
            ConsoleEvents::ERROR => [
                ['notifyConsoleException', 0],
            ],
        ];
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
        if ($event instanceof GetResponseForExceptionEvent) {
            return $event->getException();
        }
        if ($event instanceof ExceptionEvent) {
            return $event->getThrowable();
        }

        throw new InvalidArgumentException(sprintf(
            'Invalid event object. Allowed: %s or %s',
            GetResponseForExceptionEvent::class,
            ExceptionEvent::class,
        ));
    }
}
