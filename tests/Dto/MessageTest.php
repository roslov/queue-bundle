<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Dto\Message;
use stdClass;

/**
 * Tests Message DTO.
 */
final class MessageTest extends TestCase
{
    /**
     * Tests getters and setters of Message.
     */
    public function testGettersAndSetters(): void
    {
        $message = new Message();
        $payload = new stdClass();

        $message->setType('TestType');
        $message->setSource('test-service');
        $message->setCorrelationId('corr-123');
        $message->setData($payload);

        $this->assertSame('TestType', $message->getType());
        $this->assertSame('test-service', $message->getSource());
        $this->assertSame('corr-123', $message->getCorrelationId());
        $this->assertSame($payload, $message->getData());
    }
}
