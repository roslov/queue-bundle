<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Tests\Repository\EventRepositoryTest;

// phpcs:disable Generic.Files.LineLength.TooLong
/**
 * @extends EntityRepository<Event>
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Event[] findAll()
 * @method Event[] findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, int|null $limit = null, int|null $offset = null)
 * @see EventRepositoryTest
 */
final class EventRepository extends EntityRepository
{
}
