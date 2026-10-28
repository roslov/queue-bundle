<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Dto\Error;

/**
 * Tests Error DTO.
 */
final class ErrorTest extends TestCase
{
    /**
     * Tests getters and setters of Error.
     */
    public function testGettersAndSetters(): void
    {
        $error = new Error();
        $error->setType('NotFound');
        $error->setMessage('Resource not found.');

        $this->assertSame('NotFound', $error->getType());
        $this->assertSame('Resource not found.', $error->getMessage());
    }
}
