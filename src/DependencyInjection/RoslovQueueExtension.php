<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\DependencyInjection;

use Roslov\QueueBundle\Producer\ExceptionThrownProducer;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Queue extension.
 */
final class RoslovQueueExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Alias
     */
    private const ALIAS = 'roslov_queue';

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $emRef = $config['entity_manager'] !== null ? new Reference($config['entity_manager']) : null;

        $container->getDefinition('roslov_queue.subscriber.event_sender')
            ->replaceArgument(1, $config['event_processor']['delayed_delivery_subscriber']);
        $container->getDefinition('roslov_queue.message_factory')
            ->replaceArgument(1, $config['service_name']);
        $container->getDefinition('roslov_queue.payload_factory')
            ->replaceArgument(0, $config['payload_mapping']);
        $container->getDefinition('roslov_queue.rabbitmq.simple_ssl_context_provider')
            ->replaceArgument(0, $config['ssl_enabled']);
        $container->getDefinition('roslov_queue.rpc.server_callback')
            ->replaceArgument(1, $emRef)
            ->replaceArgument(2, $config['rpc_server']['enabled'])
            ->replaceArgument(3, $this->getHandlerReferences($config['rpc_server']['handlers'] ?? []))
            ->replaceArgument(
                4,
                $config['rpc_server']['setup'] ? new Reference($config['rpc_server']['setup']) : null
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
                    : null
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
     * @param array<string, string> $serverConfig RPC server config
     * @return string Queue name
     */
    private function getServerQueueName(array $serverConfig): string
    {
        return 'roslov-queue-' . md5($serverConfig['connection'] . $serverConfig['exchange']);
    }
}
