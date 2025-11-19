<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Processor;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Psr\Log\LoggerInterface;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Exception\EventStorageFailedException;
use Roslov\QueueBundle\Producer\BaseProducer;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Throwable;

/**
 * Stores events into DB and sends them to the queue.
 */
final class EventProcessor
{
    /**
     * @var bool Whether event processor is enabled. If disabled, no events will be sent or saved
     */
    private bool $enabled;

    /**
     * @var bool Whether event processor uses instant delivery. If disabled, the event processor is used as
     * transactional outbox
     */
    private bool $instantDelivery;

    /**
     * @var ProducerLocator Producer locator
     */
    private ProducerLocator $producerLocator;

    /**
     * @var EntityManagerInterface|null Entity manager
     */
    private ?EntityManagerInterface $em = null;

    /**
     * @var LoggerInterface Logger
     */
    private LoggerInterface $logger;

    /**
     * @var MessagePayloadSerializer Message payload serializer
     */
    private MessagePayloadSerializer $serializer;

    /**
     * @var LogObfuscator Log obfuscator
     */
    private LogObfuscator $obfuscator;

    /**
     * @var Event[] Events stored for later persisting and flushing
     */
    private static array $events = [];

    /**
     * Constructor.
     *
     * @param bool $enabled Whether event processor is enabled. If disabled, no events will be sent or saved
     * @param bool $instantDelivery Whether event processor uses instant delivery. If disabled, the event processor is
     * used as transactional outbox
     * @param ProducerLocator $producerLocator Producer locator
     * @param EntityManagerInterface|null $em Entity manager
     * @param LoggerInterface $logger Logger
     * @param MessagePayloadSerializer $serializer Message payload serializer
     * @param LogObfuscator $obfuscator Log obfuscator
     */
    public function __construct(
        bool $enabled,
        bool $instantDelivery,
        ProducerLocator $producerLocator,
        ?EntityManagerInterface $em,
        LoggerInterface $logger,
        MessagePayloadSerializer $serializer,
        LogObfuscator $obfuscator
    ) {
        $this->enabled = $enabled;
        $this->instantDelivery = $instantDelivery;
        $this->producerLocator = $producerLocator;
        $this->obfuscator = $obfuscator;
        $this->serializer = $serializer;
        $this->logger = $logger;
        $this->em = $em;
    }

    /**
     * Saves the message into DB for later sending.
     *
     * Note that some transactional lifecycle events cannot be persisted in a transaction, so they should be persisted
     * and flushed right before the commit of transaction.
     *
     * @param string $producerName Producer name
     * @param object $payload Message payload
     * @param bool $sendImmediately Whether to send the message immediately.
     * This can be useful for sending exception alerts or when the queue storage fails
     */
    public function save(string $producerName, object $payload, bool $sendImmediately = false): void
    {
        if (!$this->enabled) {
            return;
        }

        $body = $this->serializer->serialize($payload);

        if ($this->instantDelivery) {
            $this->send($producerName, $body);
            return;
        }

        if ($sendImmediately) {
            try {
                $this->logger->debug('The queue message is forced to be sent immediately.');
                $this->send($producerName, $body);
                return;
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    'Failed to send the queue message immediately. Reason: %s',
                    $e->getMessage(),
                ));
            }
        }

        $this->logger->debug(sprintf(
            'Saving the queue message via the producer "%s"... Payload: %s',
            $producerName,
            $body
        ));

        $event = new Event();
        $event->setProducerName($producerName);
        $event->setBody($body);
        $event->setMicrotime(microtime(true));

        $this->storeEvent($event);
    }

    /**
     * Persists and flushes all prepared events.
     *
     * This method should be called right before the commit of transaction.
     *
     * @throws EventStorageFailedException If an error occurs during saving the message to DB
     */
    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!self::$events) {
            $this->logger->debug('There is no event to persist.');
            return;
        }

        $this->logger->debug('Persisting the events...');
        try {
            foreach (self::$events as $event) {
                $this->getEntityManager()->persist($event);
            }
            $this->getEntityManager()->flush();
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Could not store queue messages. Reason: %s', $e->getMessage()));
            throw new EventStorageFailedException(message: $e->getMessage(), previous: $e);
        }
        self::$events = [];
        $this->logger->debug('Saved.');
    }

    /**
     * Sends all previously stored events.
     *
     * This method should be called on kernel terminate or on similar cases, so all events are sent after common program
     * execution.
     *
     * Note that this method clears entity manager, so all previously persisted but not flushed queries will be lost.
     * This is needed in order to avoid saving entities that the main program was not planning to flush.
     *
     * @param bool $dryRun Dry run — if enabled then events will be kept in DB and not sent. It is useful for tests
     */
    public function sendAll(bool $dryRun = false): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->instantDelivery) {
            $this->logger->info('Instant delivery: sending skipped because events are sent in real-time.');
            return;
        }

        $this->getEntityManager()->clear();
        $this->flush();

        if ($dryRun) {
            $this->logger->info('Dry run: sending skipped.');
            return;
        }

        while ($event = $this->getEntityManager()->getRepository(Event::class)->findOneBy([], ['microtime' => 'ASC'])) {
            $this->send($event->getProducerName(), $event->getBody());
            $this->getEntityManager()->remove($event);
            $this->getEntityManager()->flush();
            $this->logger->debug('Removed from DB.');
        }
    }

    /**
     * Stores the event for later sending.
     *
     * @param Event $event Event
     *
     * @throws EventStorageFailedException If an error occurs during saving the message to DB
     */
    private function storeEvent(Event $event): void
    {
        if ($this->getEntityManager()->getConnection()->isTransactionActive()) {
            self::$events[] = $event;
            $this->logger->debug('Prepared for saving.');

            return;
        }

        try {
            $this->getEntityManager()->persist($event);
            $this->getEntityManager()->flush();
            $this->logger->debug('Saved.');
        } catch (Throwable $e) {
            $this->logger->error(sprintf('Could not store the queue message. Reason: %s', $e->getMessage()));
            throw new EventStorageFailedException(message: $e->getMessage(), previous: $e);
        }
    }

    /**
     * Returns the producer.
     *
     * @param string $producerName Producer name
     * @return BaseProducer Producer
     */
    private function getProducer(string $producerName): BaseProducer
    {
        return $this->producerLocator->get($producerName);
    }

    /**
     * Returns the entity manager.
     *
     * @return EntityManagerInterface Entity manager
     */
    private function getEntityManager(): EntityManagerInterface
    {
        if ($this->em === null) {
            throw new LogicException(
                'You cannot use the event processor with delayed delivery without providing an entity manager.'
            );
        }
        return $this->em;
    }

    /**
     * Sends the event instantly.
     *
     * @param string $producerName Producer name
     * @param string $body Full JSON-encoded body
     */
    private function send(string $producerName, string $body): void
    {
        $this->logger->info(sprintf(
            'Sending the queue message via the producer "%s"... Payload: %s',
            $producerName,
            $this->obfuscator->obfuscate($body)
        ));
        $this->getProducer($producerName)->send($body);
        $this->logger->info('Sent.');
    }
}
