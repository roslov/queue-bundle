<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Fixture: Dummy conflicting DTO.
 */
#[AsQueuePayload('Dummy')]
final class DummyConflictingDto
{
}
