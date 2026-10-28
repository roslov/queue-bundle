<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Dto;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Dto\ExceptionThrown;

/**
 * Tests ExceptionThrown DTO.
 */
final class ExceptionThrownTest extends TestCase
{
    /**
     * Tests getters and setters of ExceptionThrown.
     */
    public function testGettersAndSetters(): void
    {
        $dto = new ExceptionThrown();
        $dto->setExceptionClass('RuntimeException');
        $dto->setFile('/app/src/Foo.php');
        $dto->setLine(42);
        $dto->setMessage('Something failed');
        $dto->setTrace('#0 /app/src/Foo.php(42): test()');
        $dto->setServiceName('test_service');
        $dto->setHostName('host.local');
        $dto->setUri('/api/v1/test?foo=bar');

        $this->assertSame('RuntimeException', $dto->getExceptionClass());
        $this->assertSame('/app/src/Foo.php', $dto->getFile());
        $this->assertSame(42, $dto->getLine());
        $this->assertSame('Something failed', $dto->getMessage());
        $this->assertSame('#0 /app/src/Foo.php(42): test()', $dto->getTrace());
        $this->assertSame('test_service', $dto->getServiceName());
        $this->assertSame('host.local', $dto->getHostName());
        $this->assertSame('/api/v1/test?foo=bar', $dto->getUri());
    }

    /**
     * Tests default null values for nullable properties.
     */
    public function testDefaultNullableValues(): void
    {
        $dto = new ExceptionThrown();

        $this->assertNull($dto->getHostName());
        $this->assertNull($dto->getUri());
    }
}
