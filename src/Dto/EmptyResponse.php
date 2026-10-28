<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Dto;

use Roslov\QueueBundle\Attribute\AsQueuePayload;

/**
 * Response DTO: Empty response
 */
#[AsQueuePayload('Response.Empty')]
final class EmptyResponse
{
}
