<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Dto\Trigger;

/**
 * Tests Trigger DTO.
 */
final class TriggerTest extends TestCase
{
    /**
     * Tests instantiation of Trigger.
     */
    public function testInstantiation(): void
    {
        $trigger = new Trigger();

        $this->assertInstanceOf(Trigger::class, $trigger);
    }
}
