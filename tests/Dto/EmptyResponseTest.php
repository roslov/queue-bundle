<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Dto\EmptyResponse;

/**
 * Tests EmptyResponse DTO.
 */
final class EmptyResponseTest extends TestCase
{
    /**
     * Tests instantiation of EmptyResponse.
     */
    public function testInstantiation(): void
    {
        $response = new EmptyResponse();

        $this->assertInstanceOf(EmptyResponse::class, $response);
    }
}
