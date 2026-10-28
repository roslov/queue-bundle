<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Trigger DTO: This DTO does not have any fields because it just initiates a process in some queue.
 */
#[AsQueuePayload('Trigger')]
final class Trigger
{
}
