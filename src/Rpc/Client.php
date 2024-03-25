<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Rpc;

use LogicException;
use OldSound\RabbitMqBundle\RabbitMq\RpcClient;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use Roslov\QueueBundle\Exception\RequestIdNotFoundRpcClientException;
use Roslov\QueueBundle\Exception\TimeoutRpcClientException;
use Roslov\QueueBundle\Generator\IdGeneratorInterface;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;

/**
 * RPC client.
 */
final class Client implements ClientInterface
{
    /**
     * @var RpcClient|null RPC client
     */
    private ?RpcClient $client = null;

    /**
     * @var MessagePayloadSerializer Message payload serializer
     */
    private MessagePayloadSerializer $serializer;

    /**
     * @var IdGeneratorInterface Id generator
     */
    private IdGeneratorInterface $idGenerator;

    /**
     * Constructor.
     *
     * @param RpcClient|null $client RPC client
     * @param MessagePayloadSerializer $serializer Message payload serializer
     * @param IdGeneratorInterface $idGenerator Id generator
     */
    public function __construct(
        ?RpcClient $client,
        MessagePayloadSerializer $serializer,
        IdGeneratorInterface $idGenerator
    ) {
        $this->client = $client;
        $this->serializer = $serializer;
        $this->idGenerator = $idGenerator;
    }

    /**
     * @inheritDoc
     */
    public function call(object $command, string $exchangeName, int $timeout = self::TIMEOUT): object
    {
        if ($this->client === null) {
            throw new LogicException('RPC client should be set.');
        }
        $message = $this->serializer->serialize($command);
        $requestId = $this->idGenerator->generateId();
        $this->client->addRequest($message, $exchangeName, $requestId, '', $timeout);
        try {
            $replies = $this->client->getReplies();
        } catch (AMQPTimeoutException $e) {
            throw new TimeoutRpcClientException($e->getMessage());
        }
        if (!isset($replies[$requestId])) {
            throw new RequestIdNotFoundRpcClientException(
                sprintf('RPC call response does not contain request id "%s".', $requestId)
            );
        }
        return $this->serializer->deserialize($replies[$requestId]);
    }
}
