<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall

namespace Roslov\QueueBundle\Rpc;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage;
use Roslov\QueueBundle\Exception\InvalidHandlerRpcServerException;
use Roslov\QueueBundle\Exception\UnsupportedCommandRpcServerException;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;

/**
 * RPC server.
 */
final class Server
{
    /**
     * @var MessagePayloadSerializer Message payload serializer
     */
    private MessagePayloadSerializer $serializer;

    /**
     * @var EntityManagerInterface|null Entity manager
     */
    private ?EntityManagerInterface $em = null;

    /**
     * @var bool Whether RPC server is enabled
     */
    private bool $enabled;

    /**
     * @var array<string, HandlerInterface> Handlers (command class name => handler service)
     */
    private array $handlers;

    /**
     * @var object|null The callable object that has to be run before message processing (for example, DB connection
     * refresh)
     */
    private ?object $setup = null;

    /**
     * Constructor.
     *
     * @param MessagePayloadSerializer $serializer Message payload serializer
     * @param EntityManagerInterface|null $em Entity manager
     * @param bool $enabled Whether RPC server is enabled
     * @param array<string, HandlerInterface> $handlers Handlers (command class name => handler service)
     * @param object|null $setup The callable object that has to be run before message processing (for example, DB
     * connection refresh)
     */
    public function __construct(
        MessagePayloadSerializer $serializer,
        ?EntityManagerInterface $em,
        bool $enabled,
        array $handlers,
        ?object $setup
    ) {
        $this->em = $em;
        $this->serializer = $serializer;
        $this->enabled = $enabled;
        $this->handlers = $handlers;
        $this->setup = $setup;
    }

    /**
     * Processes the message and returns the serialized result.
     *
     * @param AMQPMessage $msg Message
     * @return string Serialized response
     */
    public function execute(AMQPMessage $msg): string
    {
        return $this->processMessage($msg);
    }

    /**
     * Processes the message and returns the serialized result.
     *
     * @param AMQPMessage $msg Message
     * @return string Serialized response
     */
    private function processMessage(AMQPMessage $msg): string
    {
        if (!$this->enabled) {
            throw new LogicException('The RPC server should be enabled in the configuration.');
        }
        if (is_callable($this->setup)) {
            ($this->setup)();
        }
        $this->refreshEntityManager();
        $command = $this->serializer->deserialize($msg->getBody());
        $result = $this->handle($command);
        return $this->serializer->serialize($result);
    }

    /**
     * Processes the command and returns the result.
     *
     * @param object $command Command
     * @return object Result of the command execution
     */
    private function handle(object $command): object
    {
        $class = get_class($command);
        $handler = $this->handlers[$class] ?? null;
        if ($handler !== null) {
            if (!$handler instanceof HandlerInterface) {
                throw new InvalidHandlerRpcServerException(sprintf(
                    'Handler "%s" should implement "%s".',
                    $class,
                    HandlerInterface::class
                ));
            }
            return $handler->handle($command);
        }
        throw new UnsupportedCommandRpcServerException(sprintf('Command "%s" is not supported.', $class));
    }

    /**
     * Refreshes entity manager to avoid DB inconsistency if DB is updated in another thread.
     */
    private function refreshEntityManager(): void
    {
        if ($this->em !== null) {
            $this->em->clear();
        }
    }
}
