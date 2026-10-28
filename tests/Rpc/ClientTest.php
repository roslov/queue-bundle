<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\Rpc;

use LogicException;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Dto\EmptyResponse;
use Roslov\QueueBundle\Exception\RequestIdNotFoundRpcClientException;
use Roslov\QueueBundle\Exception\TimeoutRpcClientException;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Generator\IdGeneratorInterface;
use Roslov\QueueBundle\Rpc\Client;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Tests RPC Client.
 */
final class ClientTest extends TestCase
{
    private MessagePayloadSerializer $serializer;

    /**
     * Tests successful RPC call.
     */
    public function testCallSuccess(): void
    {
        $idGenerator = $this->createStub(IdGeneratorInterface::class);
        $idGenerator->method('generateId')->willReturn('req-42');

        $replyJson = $this->serializer->serialize(new EmptyResponse());

        $rpcClient = $this->createMock(RpcClient::class);
        $rpcClient->expects($this->once())
            ->method('addRequest')
            ->with(
                $this->callback(static function (string $msg): bool {
                    /** @var array{type: string} $decoded */
                    $decoded = json_decode($msg, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded['type'] === 'DummyCommand';
                }),
                'test_exchange',
                'req-42',
                '',
                10,
            );
        $rpcClient->expects($this->once())
            ->method('getReplies')
            ->willReturn(['req-42' => $replyJson]);

        $client = new Client($rpcClient, $this->serializer, $idGenerator);
        $result = $client->call(new DummyCommandDto(), 'test_exchange', 10);

        $this->assertInstanceOf(EmptyResponse::class, $result);
    }

    /**
     * Tests call throws LogicException when RPC client is null.
     */
    public function testCallThrowsWhenClientIsNull(): void
    {
        $idGenerator = $this->createStub(IdGeneratorInterface::class);
        $client = new Client(null, $this->serializer, $idGenerator);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('RPC client should be set.');

        $client->call(new DummyCommandDto(), 'test_exchange');
    }

    /**
     * Tests call throws TimeoutRpcClientException on AMQP timeout.
     */
    public function testCallThrowsOnTimeout(): void
    {
        $idGenerator = $this->createStub(IdGeneratorInterface::class);
        $idGenerator->method('generateId')->willReturn('req-1');

        $rpcClient = $this->createMock(RpcClient::class);
        $rpcClient->expects($this->once())
            ->method('getReplies')
            ->willThrowException(new AMQPTimeoutException('Timeout occurred'));

        $client = new Client($rpcClient, $this->serializer, $idGenerator);

        $this->expectException(TimeoutRpcClientException::class);
        $this->expectExceptionMessage('Timeout occurred');

        $client->call(new DummyCommandDto(), 'test_exchange');
    }

    /**
     * Tests call throws RequestIdNotFoundRpcClientException when reply does not contain request id.
     */
    public function testCallThrowsWhenRequestIdNotFoundInReplies(): void
    {
        $idGenerator = $this->createStub(IdGeneratorInterface::class);
        $idGenerator->method('generateId')->willReturn('req-missing');

        $rpcClient = $this->createMock(RpcClient::class);
        $rpcClient->expects($this->once())
            ->method('getReplies')
            ->willReturn(['other-id' => '{}']);

        $client = new Client($rpcClient, $this->serializer, $idGenerator);

        $this->expectException(RequestIdNotFoundRpcClientException::class);
        $this->expectExceptionMessage('RPC call response does not contain request id "req-missing".');

        $client->call(new DummyCommandDto(), 'test_exchange');
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $payloadFactory = new PayloadFactory(
            Configuration::DEFAULT_PAYLOADS + ['DummyCommand' => DummyCommandDto::class],
        );
        $messageFactory = new MessageFactory($payloadFactory, 'client_service');
        $this->serializer = new MessagePayloadSerializer($payloadFactory, $messageFactory);
    }
}
