<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Fixtures;

use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Fixture: Dummy custom error DTO overriding default Error.
 */
#[AsQueuePayload('Error')]
final class DummyCustomErrorDto
{
}
