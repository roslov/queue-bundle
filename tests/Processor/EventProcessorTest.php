<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Processor;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use LogicException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use ReflectionProperty;
use Roslov\LogObfuscator\LogObfuscator;
use Roslov\QueueBundle\Entity\Event;
use Roslov\QueueBundle\Exception\EventStorageFailedException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyProducer;
use RuntimeException;

/**
 * Tests EventProcessor.
 */
final class EventProcessorTest extends TestCase
{
    private MessagePayloadSerializer $serializer;

    /**
     * Tests that save does nothing when processor is disabled.
     */
    public function testSaveWhenDisabledDoesNothing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $this->createProcessor(false, false, null, $em)->save('dummy', new DummyDto());
    }

    /**
     * Tests that flush does nothing when processor is disabled.
     */
    public function testFlushWhenDisabledDoesNothing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $this->createProcessor(false, false, null, $em)->flush();
    }

    /**
     * Tests that sendAll does nothing when processor is disabled.
     */
    public function testSendAllWhenDisabledDoesNothing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('clear');

        $this->createProcessor(false, false, null, $em)->sendAll();
    }

    /**
     * Tests that instant delivery sends the event immediately without touching EM.
     */
    public function testInstantDeliverySendsDirectly(): void
    {
        $producer = $this->createProducerMock();
        $producer->expects($this->once())->method('send');

        $processor = $this->createProcessor(true, true, $this->createLocatorWithProducer('dummy', $producer));
        $processor->save('dummy', new DummyDto());
    }

    /**
     * Tests that sendImmediately sends directly in outbox mode when publishing succeeds.
     */
    public function testSendImmediatelySuccessInTransactionalOutboxMode(): void
    {
        $producer = $this->createProducerMock();
        $producer->expects($this->once())->method('send');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $processor = $this->createProcessor(true, false, $this->createLocatorWithProducer('dummy', $producer), $em);
        $processor->save('dummy', new DummyDto(), true);
    }

    /**
     * Tests that sendImmediately falls back to database storage when producer send fails.
     */
    public function testSendImmediatelyFallbackWhenProducerFails(): void
    {
        $producer = $this->createProducerMock();
        $producer->expects($this->once())->method('send')->willThrowException(new RuntimeException('Down'));

        $em = $this->createEmWithTransaction(false);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Event::class));
        $em->expects($this->once())->method('flush');

        $processor = $this->createProcessor(true, false, $this->createLocatorWithProducer('dummy', $producer), $em);
        $processor->save('dummy', new DummyDto(), true);
    }

    /**
     * Tests outbox mode outside transaction persists and flushes immediately.
     */
    public function testOutboxOutsideTransactionPersistsImmediately(): void
    {
        $em = $this->createEmWithTransaction(false);
        $em->expects($this->once())->method('persist')->with($this->callback(
            static fn (Event $e): bool => $e->getProducerName() === 'dummy' && $e->getBody() !== null,
        ));
        $em->expects($this->once())->method('flush');

        $this->createProcessor(true, false, null, $em)->save('dummy', new DummyDto());
    }

    /**
     * Tests outbox mode inside transaction buffers event until flush is called.
     */
    public function testOutboxInsideTransactionBuffersUntilFlush(): void
    {
        $em = $this->createEmWithTransaction(true);
        $em->expects($this->never())->method('persist');

        $this->createProcessor(true, false, null, $em)->save('dummy', new DummyDto());

        $flushEm = $this->createMock(EntityManagerInterface::class);
        $flushEm->expects($this->once())->method('persist')->with($this->isInstanceOf(Event::class));
        $flushEm->expects($this->once())->method('flush');

        $this->createProcessor(true, false, null, $flushEm)->flush();
    }

    /**
     * Tests that flush does nothing if there are no buffered events.
     */
    public function testFlushWithNoEventsDoesNothing(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $this->createProcessor(true, false, null, $em)->flush();
    }

    /**
     * Tests that flush throws EventStorageFailedException when entity manager throws.
     */
    public function testFlushThrowsEventStorageFailedExceptionOnEmError(): void
    {
        $this->createProcessor(true, false, null, $this->createEmStubWithTransaction(true))
            ->save('dummy', new DummyDto());

        $failingEm = $this->createMock(EntityManagerInterface::class);
        $failingEm->expects($this->once())->method('persist');
        $failingEm->expects($this->once())->method('flush')->willThrowException(new RuntimeException('DB error'));

        $this->expectException(EventStorageFailedException::class);
        $this->expectExceptionMessage('DB error');

        $this->createProcessor(true, false, null, $failingEm)->flush();
    }

    /**
     * Tests that storeEvent throws EventStorageFailedException when immediate flush throws.
     */
    public function testStoreEventThrowsEventStorageFailedExceptionOnEmError(): void
    {
        $em = $this->createEmWithTransaction(false);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush')->willThrowException(new RuntimeException('Immediate DB error'));

        $this->expectException(EventStorageFailedException::class);
        $this->expectExceptionMessage('Immediate DB error');

        $this->createProcessor(true, false, null, $em)->save('dummy', new DummyDto());
    }

    /**
     * Tests that save throws LogicException when entity manager is missing in delayed delivery mode.
     */
    public function testSaveThrowsLogicExceptionWhenEmMissing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'You cannot use the event processor with delayed delivery without providing an entity manager.',
        );

        $this->createProcessor(true, false)->save('dummy', new DummyDto());
    }

    /**
     * Tests sendAll does nothing when instant delivery is true.
     */
    public function testSendAllInstantDeliverySkipped(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('clear');

        $this->createProcessor(true, true, null, $em)->sendAll();
    }

    /**
     * Tests sendAll with dryRun clears EM, flushes, but skips sending stored events.
     */
    public function testSendAllDryRunSkipsSendingEvents(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->never())->method('getRepository');

        $this->createProcessor(true, false, null, $em)->sendAll(true);
    }

    /**
     * Tests sendAll sends stored events in microtime order and deletes each event from DB.
     */
    public function testSendAllSendsAndDeletesStoredEvents(): void
    {
        $event1 = new Event();
        $event1->setProducerName('p1')->setBody('{"n":1}');
        $event2 = new Event();
        $event2->setProducerName('p2')->setBody('{"n":2}');

        $prod1 = $this->createProducerMock();
        $prod1->expects($this->once())->method('send')->with('{"n":1}');
        $prod2 = $this->createProducerMock();
        $prod2->expects($this->once())->method('send')->with('{"n":2}');

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->exactly(2))->method('get')->willReturnMap([
            ['old_sound_rabbit_mq.p1_producer', $prod1],
            ['old_sound_rabbit_mq.p2_producer', $prod2],
        ]);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly(3))->method('findOneBy')->with([], ['microtime' => 'ASC'])
            ->willReturnOnConsecutiveCalls($event1, $event2, null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');
        $em->expects($this->exactly(3))->method('getRepository')->with(Event::class)->willReturn($repository);
        $em->expects($this->exactly(2))->method('remove');
        $em->expects($this->exactly(2))->method('flush');

        $this->createProcessor(true, false, new ProducerLocator($container), $em)->sendAll();
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $payloadFactory = new PayloadFactory(['Dummy' => DummyDto::class]);
        $messageFactory = new MessageFactory($payloadFactory, 'test_service');
        $this->serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $reflection = new ReflectionProperty(EventProcessor::class, 'events');
        $reflection->setValue(null, []);
    }

    /**
     * Creates an EventProcessor instance.
     *
     * @param bool $enabled Whether enabled
     * @param bool $instantDelivery Whether instant delivery
     * @param ProducerLocator|null $locator Locator
     * @param EntityManagerInterface|null $em Entity manager
     *
     * @return EventProcessor Event processor
     */
    private function createProcessor(
        bool $enabled = true,
        bool $instantDelivery = false,
        ?ProducerLocator $locator = null,
        ?EntityManagerInterface $em = null,
    ): EventProcessor {
        $locator ??= new ProducerLocator($this->createStub(ContainerInterface::class));

        return new EventProcessor(
            $enabled,
            $instantDelivery,
            $locator,
            $em,
            new NullLogger(),
            $this->serializer,
            new LogObfuscator(),
        );
    }

    /**
     * Creates a stub EntityManager with an active or inactive transaction.
     *
     * @param bool $inTransaction Whether a transaction is active
     *
     * @return EntityManagerInterface Stub entity manager
     */
    private function createEmStubWithTransaction(bool $inTransaction): EntityManagerInterface
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    /**
     * Creates a mock EntityManager with an active or inactive transaction.
     *
     * @param bool $inTransaction Whether a transaction is active
     *
     * @return EntityManagerInterface&MockObject Mock entity manager
     */
    private function createEmWithTransaction(bool $inTransaction): EntityManagerInterface&MockObject
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('isTransactionActive')->willReturn($inTransaction);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        return $em;
    }

    /**
     * Creates a mock for DummyProducer.
     *
     * @return DummyProducer&MockObject Mock producer
     */
    private function createProducerMock(): DummyProducer&MockObject
    {
        return $this->createMock(DummyProducer::class);
    }

    /**
     * Creates a ProducerLocator that returns the given producer for a specific name.
     *
     * @param string $name Short producer name
     * @param DummyProducer $producer Producer mock
     *
     * @return ProducerLocator Producer locator
     */
    private function createLocatorWithProducer(string $name, DummyProducer $producer): ProducerLocator
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with("old_sound_rabbit_mq.{$name}_producer")
            ->willReturn($producer);

        return new ProducerLocator($container);
    }
}
