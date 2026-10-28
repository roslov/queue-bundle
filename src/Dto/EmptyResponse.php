<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

use Roslov\QueueBundle\Attribute\AsQueuePayload;
use Roslov\QueueBundle\Tests\Dto\EmptyResponseTest;

/**
 * Response DTO: Empty response
 *
 * @see EmptyResponseTest
 */
#[AsQueuePayload('Response.Empty')]
final class EmptyResponse
{
}
