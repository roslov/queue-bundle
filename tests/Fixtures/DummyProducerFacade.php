<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Producer\BaseProducerFacade;

/**
 * Fixture: Dummy producer facade.
 */
final class DummyProducerFacade extends BaseProducerFacade
{
    /**
     * Public proxy to the protected send method.
     *
     * @param string $producerName Short producer name
     * @param object $payload Message payload
     * @param bool $sendImmediately Whether to send the message immediately
     */
    public function sendEvent(string $producerName, object $payload, bool $sendImmediately = false): void
    {
        $this->send($producerName, $payload, $sendImmediately);
    }
}
