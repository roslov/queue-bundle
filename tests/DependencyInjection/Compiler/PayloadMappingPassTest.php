<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\DependencyInjection\Compiler;

use LogicException;
use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Rpc\Server;
use Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyConflictingDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyCustomErrorDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the payload mapping compiler pass.
 */
final class PayloadMappingPassTest extends TestCase
{
    /**
     * Tests that pass does nothing if a payload factory is not defined.
     */
    public function testProcessWithoutPayloadFactory(): void
    {
        $container = new ContainerBuilder();
        $pass = new PayloadMappingPass();
        $pass->process($container);

        $this->assertFalse($container->hasDefinition('roslov_queue.payload_factory'));
    }

    /**
     * Tests that default payloads are always registered even with empty mapping.
     */
    public function testProcessWithDefaultPayloads(): void
    {
        $container = $this->createContainer();
        $pass = new PayloadMappingPass();
        $pass->process($container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);

        $this->assertSame(Configuration::DEFAULT_PAYLOADS, $mapping);
    }

    /**
     * Tests registering a DTO via the roslov_queue.payload tag.
     */
    public function testProcessWithTaggedPayload(): void
    {
        $container = $this->createContainer();

        $dtoDef = new Definition(DummyDto::class);
        $dtoDef->addTag('roslov_queue.payload', [
            'type' => 'Dummy',
            'class' => DummyDto::class,
        ]);
        $container->setDefinition(DummyDto::class, $dtoDef);

        $pass = new PayloadMappingPass();
        $pass->process($container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);

        $this->assertArrayHasKey('Dummy', $mapping);
        $this->assertSame(DummyDto::class, $mapping['Dummy']);
        $this->assertFalse($dtoDef->hasTag('roslov_queue.payload'));
    }

    /**
     * Tests registering an RPC command and its handler via tag.
     */
    public function testProcessWithTaggedRpcCommand(): void
    {
        $container = $this->createContainer();

        $cmdDef = new Definition(DummyCommandDto::class);
        $cmdDef->addTag('roslov_queue.payload', [
            'type' => 'DummyCommand',
            'class' => DummyCommandDto::class,
            'rpc_handler' => DummyHandler::class,
        ]);
        $container->setDefinition(DummyCommandDto::class, $cmdDef);

        $pass = new PayloadMappingPass();
        $pass->process($container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);
        $this->assertSame(DummyCommandDto::class, $mapping['DummyCommand']);

        /** @var array<string, Reference> $handlers */
        $handlers = $container->getDefinition('roslov_queue.rpc.server_callback')->getArgument(3);
        $this->assertArrayHasKey(DummyCommandDto::class, $handlers);
        $this->assertSame(DummyHandler::class, (string) $handlers[DummyCommandDto::class]);
    }

    /**
     * Tests that explicit YAML configuration takes precedence over attributes.
     */
    public function testProcessExplicitConfigWinsOverAttribute(): void
    {
        $container = $this->createContainer(['Dummy' => 'App\Dto\ExplicitDummy']);
        $container->setParameter(PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM, ['Dummy']);

        $dtoDef = new Definition(DummyDto::class);
        $dtoDef->addTag('roslov_queue.payload', [
            'type' => 'Dummy',
            'class' => DummyDto::class,
        ]);
        $container->setDefinition(DummyDto::class, $dtoDef);

        $pass = new PayloadMappingPass();
        $pass->process($container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);
        $this->assertSame('App\Dto\ExplicitDummy', $mapping['Dummy']);
        $this->assertFalse($container->hasParameter(PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM));
    }

    /**
     * Tests that a custom DTO attribute can override a bundle default payload.
     */
    public function testProcessAttributeOverridesDefaultPayload(): void
    {
        $container = $this->createContainer();

        $dtoDef = new Definition(DummyCustomErrorDto::class);
        $dtoDef->addTag('roslov_queue.payload', [
            'type' => 'Error',
            'class' => DummyCustomErrorDto::class,
        ]);
        $container->setDefinition(DummyCustomErrorDto::class, $dtoDef);

        $pass = new PayloadMappingPass();
        $pass->process($container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);
        $this->assertSame(DummyCustomErrorDto::class, $mapping['Error']);
    }

    /**
     * Tests that duplicate message types declared via attributes trigger a LogicException.
     */
    public function testProcessThrowsExceptionOnDuplicateType(): void
    {
        $container = $this->createContainer();

        $dto1 = new Definition(DummyDto::class);
        $dto1->addTag('roslov_queue.payload', [
            'type' => 'Dummy',
            'class' => DummyDto::class,
        ]);
        $container->setDefinition(DummyDto::class, $dto1);

        $dto2 = new Definition(DummyConflictingDto::class);
        $dto2->addTag('roslov_queue.payload', [
            'type' => 'Dummy',
            'class' => DummyConflictingDto::class,
        ]);
        $container->setDefinition(DummyConflictingDto::class, $dto2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Duplicate message type "Dummy" declared for classes "' . DummyDto::class
            . '" and "' . DummyConflictingDto::class . '".',
        );

        $pass = new PayloadMappingPass();
        $pass->process($container);
    }

    /**
     * Creates a test container with standard definitions.
     *
     * @param array<string, string> $initialMapping Initial payload mapping
     *
     * @return ContainerBuilder Container builder
     */
    private function createContainer(array $initialMapping = []): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $payloadFactoryDef = new Definition(PayloadFactory::class, [$initialMapping]);
        $container->setDefinition('roslov_queue.payload_factory', $payloadFactoryDef);

        $serverDef = new Definition(Server::class, [null, null, false, [], null]);
        $container->setDefinition('roslov_queue.rpc.server_callback', $serverDef);

        return $container;
    }
}
