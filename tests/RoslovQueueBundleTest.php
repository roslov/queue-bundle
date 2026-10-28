<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\Attribute\AsQueuePayload;
use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\DependencyInjection\RoslovQueueExtension;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\RoslovQueueBundle;
use Roslov\QueueBundle\Rpc\Server;
use Roslov\QueueBundle\Tests\Fixtures\DummyCommandDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyDto;
use Roslov\QueueBundle\Tests\Fixtures\DummyHandler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function dirname;

/**
 * Tests the RoslovQueueBundle.
 */
final class RoslovQueueBundleTest extends TestCase
{
    /**
     * Tests that build() registers attribute autoconfiguration and compiler pass.
     */
    public function testBuildRegistersAutoconfigurationAndCompilerPass(): void
    {
        $container = new ContainerBuilder();
        $bundle = new RoslovQueueBundle();
        $bundle->build($container);

        $this->assertArrayHasKey(AsQueuePayload::class, $container->getAutoconfiguredAttributes());

        $hasPass = false;
        foreach ($container->getCompilerPassConfig()->getBeforeOptimizationPasses() as $pass) {
            if ($pass instanceof PayloadMappingPass) {
                $hasPass = true;

                break;
            }
        }

        $this->assertTrue($hasPass, 'PayloadMappingPass should be registered.');
    }

    /**
     * Tests full container compilation with autoconfigured DTOs.
     */
    public function testFullContainerCompilation(): void
    {
        $container = new ContainerBuilder();

        $bundle = new RoslovQueueBundle();
        $bundle->build($container);

        // Required service definitions
        $factoryDef = new Definition(PayloadFactory::class, [[]]);
        $factoryDef->setPublic(true);

        $container->setDefinition('roslov_queue.payload_factory', $factoryDef);

        $serverDef = new Definition(Server::class, [null, null, true, [], null]);
        $serverDef->setPublic(true);

        $container->setDefinition('roslov_queue.rpc.server_callback', $serverDef);

        // Handler definition
        $handlerDef = new Definition(DummyHandler::class);
        $handlerDef->setPublic(true);
        $handlerDef->setAutoconfigured(true);

        $container->setDefinition(DummyHandler::class, $handlerDef);

        // Autoconfigured DTOs
        $dtoDef = new Definition(DummyDto::class);
        $dtoDef->setAutoconfigured(true);

        $container->setDefinition(DummyDto::class, $dtoDef);

        $cmdDef = new Definition(DummyCommandDto::class);
        $cmdDef->setAutoconfigured(true);

        $container->setDefinition(DummyCommandDto::class, $cmdDef);

        $container->compile(true);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);

        // Check defaults are present
        foreach (Configuration::DEFAULT_PAYLOADS as $type => $class) {
            $this->assertArrayHasKey($type, $mapping);
            $this->assertSame($class, $mapping[$type]);
        }

        // Check attribute DTOs are mapped
        $this->assertArrayHasKey('Dummy', $mapping);
        $this->assertSame(DummyDto::class, $mapping['Dummy']);

        $this->assertArrayHasKey('DummyCommand', $mapping);
        $this->assertSame(DummyCommandDto::class, $mapping['DummyCommand']);

        // Check RPC handler is mapped
        /** @var array<string, Reference> $handlers */
        $handlers = $container->getDefinition('roslov_queue.rpc.server_callback')->getArgument(3);
        $this->assertArrayHasKey(DummyCommandDto::class, $handlers);
        $this->assertSame(DummyHandler::class, (string) $handlers[DummyCommandDto::class]);
    }

    /**
     * Tests that getPath returns the bundle root directory.
     */
    public function testGetPath(): void
    {
        $bundle = new RoslovQueueBundle();

        $this->assertSame(dirname(__DIR__), $bundle->getPath());
    }

    /**
     * Tests that getContainerExtension returns RoslovQueueExtension instance.
     */
    public function testGetContainerExtension(): void
    {
        $bundle = new RoslovQueueBundle();

        $this->assertInstanceOf(RoslovQueueExtension::class, $bundle->getContainerExtension());
    }
}
