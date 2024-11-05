<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Entity;

use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Roslov\QueueBundle\Repository\EventRepository;

/**
 * Event
 *
 * This entity contains an event to be sent to RabbitMQ using the Transactional Outbox pattern.
 *
 * @ORM\Entity(repositoryClass=EventRepository::class)
 * @ORM\Table(name="event")
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
class Event
{
    /**
     * @var int|null Id
     */
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Id]
    private ?int $id = null;

    /**
     * @var float|null Unix timestamp with microseconds when the event was saved
     */
    #[ORM\Column(type: 'float')]
    private ?float $microtime = null;

    /**
     * @var string|null Producer name
     */
    #[ORM\Column(type: 'string', length: 64)]
    private ?string $producerName = null;

    /**
     * @var string|null Full message body
     */
    #[ORM\Column(type: 'string', length: 4096)]
    private ?string $body = null;

    /**
     * @var DateTimeInterface|null Creation timestamp
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    #[ORM\GeneratedValue]
    private ?DateTimeInterface $createdAt = null;

    /**
     * @var DateTimeInterface|null Update timestamp
     */
    #[ORM\Column(type: 'datetime', nullable: false)]
    #[ORM\GeneratedValue]
    private ?DateTimeInterface $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getMicrotime(): ?float
    {
        return $this->microtime;
    }

    public function setMicrotime(?float $microtime): self
    {
        $this->microtime = $microtime;
        return $this;
    }

    public function getProducerName(): ?string
    {
        return $this->producerName;
    }

    public function setProducerName(?string $producerName): self
    {
        $this->producerName = $producerName;
        return $this;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;
        return $this;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
