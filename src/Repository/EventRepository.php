<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Roslov\QueueBundle\Entity\Event;

/**
 * @method Event|null find($id, $lockMode = null, $lockVersion = null)
 * @method Event|null findOneBy(array $criteria, array $orderBy = null)
 * @method Event[]    findAll()
 * @method Event[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
final class EventRepository extends EntityRepository
{
}
