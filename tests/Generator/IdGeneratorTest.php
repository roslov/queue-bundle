<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Generator\IdGenerator;

use function strlen;

/**
 * Tests IdGenerator.
 */
final class IdGeneratorTest extends TestCase
{
    /**
     * Tests that generateId returns a non-empty 32-character MD5 hash string.
     */
    public function testGenerateIdReturnsMd5String(): void
    {
        $generator = new IdGenerator();
        $id1 = $generator->generateId();
        $id2 = $generator->generateId();

        $this->assertSame(32, strlen($id1));
        $this->assertSame(32, strlen($id2));
        $this->assertNotSame($id1, $id2);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id1);
    }
}
