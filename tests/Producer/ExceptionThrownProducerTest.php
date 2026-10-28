<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Producer;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Roslov\QueueBundle\Producer\ExceptionThrownProducer;

/**
 * Tests ExceptionThrownProducer.
 */
final class ExceptionThrownProducerTest extends TestCase
{
    /**
     * Tests that ExceptionThrownProducer returns the exception-thrown routing key.
     */
    public function testGetRoutingKey(): void
    {
        $reflectionClass = new ReflectionClass(ExceptionThrownProducer::class);
        $producer = $reflectionClass->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(ExceptionThrownProducer::class, 'getRoutingKey');
        $routingKey = $method->invoke($producer);

        $this->assertSame('exception-thrown', $routingKey);
    }
}
