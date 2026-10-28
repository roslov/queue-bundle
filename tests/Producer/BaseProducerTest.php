<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Producer;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;

/**
 * Tests BaseProducer.
 */
final class BaseProducerTest extends TestCase
{
    /**
     * Tests that send sets application/json content type and publishes to routing key.
     */
    public function testSend(): void
    {
        $producer = $this->getMockBuilder(DummyProducer::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setContentType', 'publish'])
            ->getMock();

        $producer->expects($this->once())
            ->method('setContentType')
            ->with('application/json');

        $producer->expects($this->once())
            ->method('publish')
            ->with('{"key":"value"}', 'dummy-routing-key');

        $producer->send('{"key":"value"}');
    }
}
