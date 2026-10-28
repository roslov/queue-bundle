<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\DependencyInjection;

use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\Producer\ExceptionThrownProducer;
use Roslov\QueueBundle\Tests\DependencyInjection\RoslovQueueExtensionTest;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

use function array_keys;
use function array_merge;
use function is_array;
use function ltrim;
use function md5;

/**
 * Queue extension.
 *
 * @see RoslovQueueExtensionTest
 */
final class RoslovQueueExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Alias
     */
    private const string ALIAS = 'roslov_queue';

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        /**
         * @var array{
         *     logger: string,
         *     entity_manager: string|null,
         *     event_processor: array{
         *         enabled: bool,
         *         delayed_delivery_subscriber: string|null,
         *         instant_delivery: bool
         *     },
         *     service_name: string,
         *     ssl_enabled: bool,
         *     rpc_server: array{
         *         enabled: bool,
         *         handlers?: array<string, string>,
         *         setup: string|null
         *     },
         *     rpc_client: array{
         *         enabled: bool
         *     },
         *     exception_subscriber: array{
         *         enabled: bool,
         *         exception_validator: string|null
         *     },
         *     payload_mapping: array<string, string>
         * } $config
         * @phpstan-var array<array<string, mixed>> $configs
         */
        $config = $this->processConfiguration($configuration, $configs);

        $emRef = $config['entity_manager'] !== null ? new Reference($config['entity_manager']) : null;

        $configuredPayloadTypes = $this->getConfiguredPayloadTypes($configs);
        if ($configuredPayloadTypes !== []) {
            $container->setParameter(
                PayloadMappingPass::CONFIGURED_PAYLOAD_TYPES_PARAM,
                $configuredPayloadTypes,
            );
        }

        $payloadMapping = array_merge(
            Configuration::DEFAULT_PAYLOADS,
            $config['payload_mapping'],
        );

        $container->getDefinition('roslov_queue.subscriber.event_sender')
            ->replaceArgument(1, $config['event_processor']['delayed_delivery_subscriber']);
        $container->getDefinition('roslov_queue.message_factory')
            ->replaceArgument(1, $config['service_name']);
        $container->getDefinition('roslov_queue.payload_factory')
            ->replaceArgument(0, $payloadMapping);
        $container->getDefinition('roslov_queue.rabbitmq.simple_ssl_context_provider')
            ->replaceArgument(0, $config['ssl_enabled']);
        $container->getDefinition('roslov_queue.rpc.server_callback')
            ->replaceArgument(1, $emRef)
            ->replaceArgument(2, $config['rpc_server']['enabled'])
            ->replaceArgument(3, $this->getHandlerReferences($config['rpc_server']['handlers'] ?? []))
            ->replaceArgument(
                4,
                $config['rpc_server']['setup'] ? new Reference($config['rpc_server']['setup']) : null,
            );
        $container->getDefinition('roslov_queue.event_processor')
            ->replaceArgument(0, $config['event_processor']['enabled'])
            ->replaceArgument(1, $config['event_processor']['instant_delivery'])
            ->replaceArgument(3, $emRef)
            ->replaceArgument(4, new Reference($config['logger']));
        $container->getDefinition('roslov_queue.exception_subscriber')
            ->replaceArgument(0, $config['exception_subscriber']['enabled'])
            ->replaceArgument(
                1,
                $config['exception_subscriber']['exception_validator']
                    ? new Reference($config['exception_subscriber']['exception_validator'])
                    : null,
            );
        $container->getDefinition('roslov_queue.exception_sender')
            ->replaceArgument(2, $config['service_name']);
        if (!$config['rpc_client']['enabled']) {
            $container->getDefinition('roslov_queue.rpc.client')
                ->replaceArgument(0, null);
        }
    }

    /**
     * @inheritDoc
     */
    public function getAlias(): string
    {
        return self::ALIAS;
    }

    /**
     * @inheritDoc
     */
    public function prepend(ContainerBuilder $container): void
    {
        /**
         * @var array{
         *     rpc_client: array{
         *         enabled: bool,
         *         connection: string|null
         *     },
         *     rpc_server: array{
         *         enabled: bool,
         *         connection: string,
         *         exchange: string
         *     },
         *     exception_sender: array{
         *         enabled: bool,
         *         connection: string,
         *         exchange: string,
         *         exchange_options: array<string, string|bool>
         *     }
         * } $config
         */
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));

        $rabbitMqConfig = [];
        if ($config['rpc_client']['enabled']) {
            $rabbitMqConfig['rpc_clients'] = [
                'roslov_queue' => [
                    'connection' => $config['rpc_client']['connection'],
                    'expect_serialized_response' => false,
                    'lazy' => true,
                ],
            ];
        }

        if ($config['rpc_server']['enabled']) {
            $rabbitMqConfig['rpc_servers'] = [
                'roslov_queue' => [
                    'connection' => $config['rpc_server']['connection'],
                    'callback' => 'roslov_queue.rpc.server_callback',
                    'qos_options' => ['prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false],
                    'exchange_options' => ['name' => $config['rpc_server']['exchange'], 'type' => 'topic'],
                    // `trim` is used to ignore extra serialization
                    'serializer' => 'trim',
                    'enable_logger' => true,
                    'queue_options' => [
                        'name' => $this->getServerQueueName($config['rpc_server']),
                    ],
                ],
            ];
        }

        $rabbitMqConfig['producers'] = [
            'event_exception_thrown' => [
                'class' => ltrim(ExceptionThrownProducer::class, '\\'),
                'connection' => $config['exception_sender']['connection'],
                'exchange_options' => $config['exception_sender']['exchange_options'],
                'enable_logger' => true,
            ],
        ];

        foreach (array_keys($container->getExtensions()) as $name) {
            if ($name === 'old_sound_rabbit_mq') {
                $container->prependExtensionConfig($name, $rabbitMqConfig);
            }
        }
    }

    /**
     * Returns references to handlers.
     *
     * @param array<string, string> $handlers Handlers (command class name => handler class name)
     *
     * @return array<string, Reference> Handler references (command class name => handler reference)
     */
    private function getHandlerReferences(array $handlers): array
    {
        $refs = [];
        foreach ($handlers as $commandClass => $handlerClass) {
            $refs[$commandClass] = new Reference($handlerClass);
        }

        return $refs;
    }

    /**
     * Generate the queue name for the RPC server.
     *
     * @param array<string, string|bool> $serverConfig RPC server config
     *
     * @return string Queue name
     */
    private function getServerQueueName(array $serverConfig): string
    {
        return 'roslov-queue-' . md5($serverConfig['connection'] . $serverConfig['exchange']);
    }

    /**
     * Returns explicitly configured payload types from raw configs.
     *
     * @param array<array<string, mixed>> $configs Raw configs
     *
     * @return list<string> Configured payload types
     */
    private function getConfiguredPayloadTypes(array $configs): array
    {
        $types = [];
        foreach ($configs as $rawConfig) {
            /** @var array<string, mixed>|null $mapping */
            $mapping = $rawConfig['payload_mapping'] ?? null;
            if (is_array($mapping)) {
                $this->collectPayloadTypes($types, $mapping);
            }
        }

        return $types;
    }

    /**
     * Collects payload types from a raw mapping array.
     *
     * @param list<string> $types Types list
     * @param array<string, mixed> $mapping Raw mapping
     */
    private function collectPayloadTypes(array &$types, array $mapping): void
    {
        foreach (array_keys($mapping) as $type) {
            $types[] = (string) $type;
        }
    }
}
