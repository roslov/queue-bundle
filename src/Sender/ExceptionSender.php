<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall

namespace Roslov\QueueBundle\Sender;

use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Throwable;

use function gethostname;

/**
 * Sends an event about an exception.
 */
final readonly class ExceptionSender
{
    /**
     * Constructor.
     *
     * @param ProducerFacade $producerFacade Producer facade
     * @param ExceptionShortener $exceptionShortener Exception shortener
     * @param string $serviceName Service name
     */
    public function __construct(
        private ProducerFacade $producerFacade,
        private ExceptionShortener $exceptionShortener,
        private string $serviceName,
    ) {
    }

    /**
     * Sends the ExceptionThrown event.
     *
     * @param Throwable $exception Exception
     */
    public function sendExceptionThrownEvent(Throwable $exception): void
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        /** @phpstan-var string|null $uri */
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        // phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $this->producerFacade->sendExceptionThrownEvent(
            $this->serviceName,
            gethostname() ?: null,
            $exception::class,
            $exception->getFile(),
            $exception->getLine(),
            $this->exceptionShortener->processMessage($exception->getMessage()),
            $this->exceptionShortener->processTrace($exception->getTraceAsString()),
            $uri ? $this->exceptionShortener->processMessage((string) $uri) : null,
        );
    }
}
