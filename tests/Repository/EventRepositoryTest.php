<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Repository\EventRepository;

/**
 * Tests EventRepository.
 */
final class EventRepositoryTest extends TestCase
{
    /**
     * Tests that EventRepository extends Doctrine EntityRepository.
     */
    public function testInstantiation(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $classMetadata = new ClassMetadata(Event::class);

        $repository = new EventRepository($em, $classMetadata);

        $this->assertInstanceOf(EntityRepository::class, $repository);
    }
}
