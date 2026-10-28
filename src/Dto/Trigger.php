<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

use Roslov\QueueBundle\Attribute\AsQueuePayload;
use Roslov\QueueBundle\Tests\Dto\TriggerTest;

/**
 * Trigger DTO: This DTO does not have any fields because it just initiates a process in some queue.
 *
 * @see TriggerTest
 */
#[AsQueuePayload('Trigger')]
final class Trigger
{
}
