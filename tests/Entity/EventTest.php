<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Entity;

use DateTime;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Entity\Event;

use const PHP_FLOAT_EPSILON;

/**
 * Tests Event entity.
 */
final class EventTest extends TestCase
{
    /**
     * Tests getters and setters of Event.
     */
    public function testGettersAndSetters(): void
    {
        $event = new Event();
        $createdAt = new DateTime('2026-01-01 00:00:00');

        $this->assertNull($event->getId());
        $this->assertNull($event->getMicrotime());
        $this->assertNull($event->getProducerName());
        $this->assertNull($event->getBody());
        $this->assertNull($event->getCreatedAt());
        $this->assertNull($event->getUpdatedAt());

        $updatedAt = new DateTime('2026-01-02 00:00:00');

        $event->setId(10);
        $event->setMicrotime(123456789.123456);
        $event->setProducerName('user_created');
        $event->setBody('{"foo":"bar"}');
        $event->setCreatedAt($createdAt);
        $event->setUpdatedAt($updatedAt);

        $this->assertSame(10, $event->getId());
        $this->assertEqualsWithDelta(123456789.123456, $event->getMicrotime(), PHP_FLOAT_EPSILON);
        $this->assertSame('user_created', $event->getProducerName());
        $this->assertSame('{"foo":"bar"}', $event->getBody());
        $this->assertSame($createdAt, $event->getCreatedAt());
        $this->assertSame($updatedAt, $event->getUpdatedAt());
    }
}
