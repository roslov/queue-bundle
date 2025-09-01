<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall

namespace Roslov\QueueBundle\Sender;

use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Producer\BaseProducerFacade;
use Throwable;

/**
 * Sends an event about an exception.
 */
final class ExceptionSender
{
    /**
     * @var BaseProducerFacade Producer facade
     */
    private BaseProducerFacade $producerFacade;

    /**
     * @var ExceptionShortener Exception shortener
     */
    private ExceptionShortener $exceptionShortener;

    /**
     * @var String $serviceName Service name
     */
    private string $serviceName;

    /**
     * Constructor.
     *
     * @param BaseProducerFacade $producerFacade Producer facade
     * @param ExceptionShortener $exceptionShortener Exception shortener
     * @param string $serviceName Service name
     */
    public function __construct(
        BaseProducerFacade $producerFacade,
        ExceptionShortener $exceptionShortener,
        string $serviceName
    ) {
        $this->producerFacade = $producerFacade;
        $this->exceptionShortener = $exceptionShortener;
        $this->serviceName = $serviceName;
    }

    /**
     * Sends the ExceptionThrown event.
     *
     * @param Throwable $exception Exception
     */
    public function sendExceptionThrownEvent(Throwable $exception): void
    {
        // phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        // phpcs:enable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable.DisallowedSuperGlobalVariable
        $this->producerFacade->sendExceptionThrownEvent(
            $this->serviceName,
            gethostname() ?: null,
            get_class($exception),
            $exception->getFile(),
            $exception->getLine(),
            $this->exceptionShortener->processMessage($exception->getMessage()),
            $this->exceptionShortener->processTrace($exception->getTraceAsString()),
            $uri ? $this->exceptionShortener->processMessage($uri) : null,
        );
    }
}
