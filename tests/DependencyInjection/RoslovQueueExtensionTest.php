<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\DependencyInjection\RoslovQueueExtension;
use Roslov\QueueBundle\Producer\ExceptionThrownProducer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

use function md5;

/**
 * Tests the RoslovQueueExtension.
 */
final class RoslovQueueExtensionTest extends TestCase
{
    /**
     * Tests that getAlias returns roslov_queue.
     */
    public function testGetAlias(): void
    {
        $extension = new RoslovQueueExtension();

        $this->assertSame('roslov_queue', $extension->getAlias());
    }

    /**
     * Tests that default payloads are loaded when payload_mapping is not configured.
     */
    public function testLoadWithDefaultPayloads(): void
    {
        $container = new ContainerBuilder();
        $extension = new RoslovQueueExtension();

        $extension->load([], $container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);

        $this->assertSame(Configuration::DEFAULT_PAYLOADS, $mapping);
        $this->assertFalse($container->hasParameter(PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM));
    }

    /**
     * Tests that configured payloads are merged with defaults and parameter is set.
     */
    public function testLoadWithCustomPayloads(): void
    {
        $container = new ContainerBuilder();
        $extension = new RoslovQueueExtension();

        $configs = [
            [
                'payload_mapping' => [
                    'Custom' => 'App\Dto\Custom',
                ],
            ],
        ];
        $extension->load($configs, $container);

        /** @var array<string, string> $mapping */
        $mapping = $container->getDefinition('roslov_queue.payload_factory')->getArgument(0);

        $this->assertArrayHasKey('Custom', $mapping);
        $this->assertSame('App\Dto\Custom', $mapping['Custom']);

        // Check defaults are also preserved
        foreach (Configuration::DEFAULT_PAYLOADS as $type => $class) {
            $this->assertArrayHasKey($type, $mapping);
            $this->assertSame($class, $mapping[$type]);
        }

        $this->assertTrue($container->hasParameter(PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM));
        $this->assertSame(['Custom'], $container->getParameter(PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM));
    }

    /**
     * Tests load with full custom configuration.
     */
    public function testLoadWithFullConfiguration(): void
    {
        $container = new ContainerBuilder();
        $extension = new RoslovQueueExtension();

        $configs = [
            [
                'service_name' => 'custom_service',
                'ssl_enabled' => true,
                'logger' => 'custom_logger',
                'entity_manager' => 'doctrine.orm.custom_entity_manager',
                'event_processor' => [
                    'enabled' => true,
                    'instant_delivery' => false,
                    'delayed_delivery_subscriber' => false,
                ],
                'rpc_client' => [
                    'enabled' => true,
                ],
                'rpc_server' => [
                    'enabled' => true,
                    'exchange' => 'rpc_exchange',
                    'setup' => 'app.rpc_setup',
                    'handlers' => [
                        'App\Dto\TestCommand' => 'App\Handler\TestHandler',
                    ],
                ],
                'exception_subscriber' => [
                    'enabled' => true,
                    'exception_validator' => 'app.exception_validator',
                ],
            ],
        ];

        $extension->load($configs, $container);

        $this->assertFalse(
            $container->getDefinition('roslov_queue.subscriber.event_sender')->getArgument(1),
        );
        $this->assertSame(
            'custom_service',
            $container->getDefinition('roslov_queue.message_factory')->getArgument(1),
        );
        $this->assertTrue(
            $container->getDefinition('roslov_queue.rabbitmq.simple_ssl_context_provider')->getArgument(0),
        );

        // Server callback definition
        $serverDef = $container->getDefinition('roslov_queue.rpc.server_callback');
        $this->assertEquals(new Reference('doctrine.orm.custom_entity_manager'), $serverDef->getArgument(1));
        $this->assertTrue($serverDef->getArgument(2));
        $this->assertEquals(
            ['App\Dto\TestCommand' => new Reference('App\Handler\TestHandler')],
            $serverDef->getArgument(3),
        );
        $this->assertEquals(new Reference('app.rpc_setup'), $serverDef->getArgument(4));

        // Event processor definition
        $epDef = $container->getDefinition('roslov_queue.event_processor');
        $this->assertTrue($epDef->getArgument(0));
        $this->assertFalse($epDef->getArgument(1));
        $this->assertEquals(new Reference('doctrine.orm.custom_entity_manager'), $epDef->getArgument(3));
        $this->assertEquals(new Reference('custom_logger'), $epDef->getArgument(4));

        // Exception subscriber and sender
        $subDef = $container->getDefinition('roslov_queue.exception_subscriber');
        $this->assertTrue($subDef->getArgument(0));
        $this->assertEquals(new Reference('app.exception_validator'), $subDef->getArgument(1));

        $senderDef = $container->getDefinition('roslov_queue.exception_sender');
        $this->assertSame('custom_service', $senderDef->getArgument(2));

        // RPC client is enabled, so argument 0 remains as default reference
        $rpcClientDef = $container->getDefinition('roslov_queue.rpc.client');
        $this->assertEquals(
            new Reference('old_sound_rabbit_mq.roslov_queue_rpc'),
            $rpcClientDef->getArgument(0),
        );
    }

    /**
     * Tests load with rpc_client disabled resets argument 0 to null.
     */
    public function testLoadWithRpcClientDisabled(): void
    {
        $container = new ContainerBuilder();
        $extension = new RoslovQueueExtension();

        $configs = [
            [
                'rpc_client' => [
                    'enabled' => false,
                ],
            ],
        ];

        $extension->load($configs, $container);

        $this->assertNull($container->getDefinition('roslov_queue.rpc.client')->getArgument(0));
    }

    /**
     * Tests prepend with default configuration prepends event_exception_thrown producer only.
     */
    public function testPrependDefaultConfiguration(): void
    {
        $container = new ContainerBuilder();
        $rabbitMqExtension = $this->createStub(ExtensionInterface::class);
        $rabbitMqExtension->method('getAlias')->willReturn('old_sound_rabbit_mq');
        $container->registerExtension($rabbitMqExtension);

        $container->prependExtensionConfig('roslov_queue', []);

        $extension = new RoslovQueueExtension();
        $extension->prepend($container);

        /** @var list<array<string, mixed>> $prependedConfigs */
        $prependedConfigs = $container->getExtensionConfig('old_sound_rabbit_mq');
        $this->assertCount(1, $prependedConfigs);

        $config = $prependedConfigs[0];
        $this->assertArrayNotHasKey('rpc_clients', $config);
        $this->assertArrayNotHasKey('rpc_servers', $config);
        $this->assertArrayHasKey('producers', $config);

        /** @var array<string, mixed> $producers */
        $producers = $config['producers'];
        $this->assertArrayHasKey('event_exception_thrown', $producers);
        $this->assertSame(
            [
                'class' => ExceptionThrownProducer::class,
                'connection' => 'default',
                'exchange_options' => [
                    'name' => 'default',
                    'type' => 'topic',
                ],
                'enable_logger' => true,
            ],
            $producers['event_exception_thrown'],
        );
    }

    /**
     * Tests prepend with RPC client and server enabled.
     */
    public function testPrependWithRpcClientAndServerEnabled(): void
    {
        $container = new ContainerBuilder();
        $rabbitMqExtension = $this->createStub(ExtensionInterface::class);
        $rabbitMqExtension->method('getAlias')->willReturn('old_sound_rabbit_mq');
        $container->registerExtension($rabbitMqExtension);

        $container->prependExtensionConfig('roslov_queue', [
            'rpc_client' => [
                'enabled' => true,
                'connection' => 'custom_rpc_conn',
            ],
            'rpc_server' => [
                'enabled' => true,
                'connection' => 'server_conn',
                'exchange' => 'server_exchange',
            ],
        ]);

        $extension = new RoslovQueueExtension();
        $extension->prepend($container);

        /** @var list<array<string, mixed>> $prependedConfigs */
        $prependedConfigs = $container->getExtensionConfig('old_sound_rabbit_mq');
        $this->assertCount(1, $prependedConfigs);

        $config = $prependedConfigs[0];
        $this->assertArrayHasKey('rpc_clients', $config);
        $this->assertArrayHasKey('rpc_servers', $config);

        /** @var array<string, mixed> $rpcClients */
        $rpcClients = $config['rpc_clients'];
        $this->assertSame(
            [
                'connection' => 'custom_rpc_conn',
                'expect_serialized_response' => false,
                'lazy' => true,
            ],
            $rpcClients['roslov_queue'],
        );

        $expectedQueueName = 'roslov-queue-' . md5('server_conn' . 'server_exchange');
        /** @var array<string, mixed> $rpcServers */
        $rpcServers = $config['rpc_servers'];
        $this->assertSame(
            [
                'connection' => 'server_conn',
                'callback' => 'roslov_queue.rpc.server_callback',
                'qos_options' => ['prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false],
                'exchange_options' => ['name' => 'server_exchange', 'type' => 'topic'],
                'serializer' => 'trim',
                'enable_logger' => true,
                'queue_options' => [
                    'name' => $expectedQueueName,
                ],
            ],
            $rpcServers['roslov_queue'],
        );
    }

    /**
     * Tests prepend does nothing when old_sound_rabbit_mq extension is not registered.
     */
    public function testPrependWithoutRabbitMqExtension(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('roslov_queue', []);

        $extension = new RoslovQueueExtension();
        $extension->prepend($container);

        $this->assertSame([], $container->getExtensionConfig('old_sound_rabbit_mq'));
    }
}
