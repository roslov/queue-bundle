<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Rpc;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Dto\EmptyResponse;
use Roslov\QueueBundle\Exception\InvalidHandlerRpcServerException;
use Roslov\QueueBundle\Exception\UnsupportedCommandRpcServerException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Rpc\HandlerInterface;
use Roslov\QueueBundle\Rpc\Server;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyHandler;
use stdClass;

/**
 * Tests RPC Server.
 */
final class ServerTest extends TestCase
{
    private MessagePayloadSerializer $serializer;

    /**
     * Tests that execute throws LogicException when server is disabled.
     */
    public function testExecuteThrowsWhenDisabled(): void
    {
        $server = new Server(
            $this->serializer,
            null,
            false,
            [],
            null,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The RPC server should be enabled in the configuration.');

        $server->execute(new AMQPMessage('{}'));
    }

    /**
     * Tests successful execution with setup callable, EM clear, and handler dispatch.
     */
    public function testExecuteSuccessWithSetupAndEm(): void
    {
        $setupCalled = false;
        $setup = static function () use (&$setupCalled): void {
            $setupCalled = true;
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('clear');

        $handler = $this->createMock(HandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($this->isInstanceOf(DummyCommandDto::class))
            ->willReturn(new EmptyResponse());

        $server = new Server(
            $this->serializer,
            $em,
            true,
            [DummyCommandDto::class => $handler],
            $setup,
        );

        $commandJson = $this->serializer->serialize(new DummyCommandDto());
        $responseJson = $server->execute(new AMQPMessage($commandJson));

        $this->assertTrue($setupCalled);
        $response = $this->serializer->deserialize($responseJson);
        $this->assertInstanceOf(EmptyResponse::class, $response);
    }

    /**
     * Tests successful execution without setup or entity manager.
     */
    public function testExecuteSuccessWithoutSetupOrEm(): void
    {
        $server = new Server(
            $this->serializer,
            null,
            true,
            [DummyCommandDto::class => new DummyHandler()],
            null,
        );

        $commandJson = $this->serializer->serialize(new DummyCommandDto());
        $responseJson = $server->execute(new AMQPMessage($commandJson));

        $response = $this->serializer->deserialize($responseJson);
        $this->assertInstanceOf(DummyCommandDto::class, $response);
    }

    /**
     * Tests that execute throws UnsupportedCommandRpcServerException when command has no handler.
     */
    public function testExecuteThrowsOnUnsupportedCommand(): void
    {
        $server = new Server(
            $this->serializer,
            null,
            true,
            [],
            null,
        );

        $commandJson = $this->serializer->serialize(new DummyCommandDto());

        $this->expectException(UnsupportedCommandRpcServerException::class);
        $this->expectExceptionMessage(
            'Command "Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto" is not supported.',
        );

        $server->execute(new AMQPMessage($commandJson));
    }

    /**
     * Tests that execute throws InvalidHandlerRpcServerException when handler does not implement HandlerInterface.
     */
    public function testExecuteThrowsOnInvalidHandler(): void
    {
        /** @var array<string, HandlerInterface> $handlers */
        $handlers = [DummyCommandDto::class => new stdClass()];

        $server = new Server(
            $this->serializer,
            null,
            true,
            $handlers,
            null,
        );

        $commandJson = $this->serializer->serialize(new DummyCommandDto());

        $this->expectException(InvalidHandlerRpcServerException::class);
        $this->expectExceptionMessage(
            'Handler "Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto" should implement "'
            . HandlerInterface::class . '".',
        );

        $server->execute(new AMQPMessage($commandJson));
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $payloadFactory = new PayloadFactory(
            Configuration::DEFAULT_PAYLOADS + [
                'DummyCommand' => DummyCommandDto::class,
                'Dummy' => DummyDto::class,
            ],
        );
        $messageFactory = new MessageFactory($payloadFactory, 'server_service');
        $this->serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);
    }
}
