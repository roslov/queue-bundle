<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Producer;

use Roslov\QueueBundle\Dto\ExceptionThrown;

/**
 * Keeps all calls to producers.
 */
final class ProducerFacade extends BaseProducerFacade
{
    /**
     * @inheritDoc
     */
    public function sendExceptionThrownEvent(
        string $serviceName,
        ?string $hostName,
        string $exceptionClass,
        string $file,
        int $line,
        string $message,
        string $trace,
        ?string $uri,
    ): void {
        $payload = new ExceptionThrown();
        $payload->setServiceName($serviceName);
        $payload->setHostName($hostName);
        $payload->setMessage($message);
        $payload->setExceptionClass($exceptionClass);
        $payload->setFile($file);
        $payload->setLine($line);
        $payload->setTrace($trace);
        $payload->setUri($uri);
        $this->send('event_exception_thrown', $payload);
    }
}
