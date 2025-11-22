<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\DependencyInjection;

use Roslov\QueueBundle\Dto\EmptyResponse;
use Roslov\QueueBundle\Dto\Error;
use Roslov\QueueBundle\Dto\ExceptionThrown;
use Roslov\QueueBundle\Dto\Trigger;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Queue configuration.
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * Tree main node name
     */
    private const NODE_NAME = 'roslov_queue';

    /**
     * Default message type to payload mapping
     */
    private const DEFAULT_PAYLOADS = [
        'Error' => Error::class,
        'Trigger' => Trigger::class,
        'Response.Empty' => EmptyResponse::class,
        'Exception.Thrown' => ExceptionThrown::class,
    ];

    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::NODE_NAME);
        $rootNode = method_exists(TreeBuilder::class, 'getRootNode')
            ? $treeBuilder->getRootNode()
            : $treeBuilder->root(self::NODE_NAME);
        $rootNode
            ->children()
                ->scalarNode('service_name')
                    ->info('The current microservice name')
                    ->example('my_service')
                    ->defaultValue('my_service')
                ->end()
                // `booleanNode()` does not work with environment variables
                ->scalarNode('ssl_enabled')
                    ->info('Whether SSL connection is enabled for RabbitMQ')
                    ->example('true')
                    ->defaultFalse()
                ->end()
                ->scalarNode('logger')
                    ->info('PSR-3 logger service')
                    ->example('logger')
                    ->defaultValue('logger')
                ->end()
                ->scalarNode('entity_manager')
                    ->info('Entity manager')
                    ->example('doctrine.orm.default_entity_manager')
                    ->defaultNull()
                ->end()
                ->arrayNode('payload_mapping')
                    ->info('Key/value pair of the mapping of message type to payload')
                    ->example(json_encode(self::DEFAULT_PAYLOADS))
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('event_processor')
                    ->info('Event processor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether event processor is enabled')
                            ->example('true')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('instant_delivery')
                            ->info('Whether instant delivery is used')
                            ->example('true')
                            ->defaultTrue()
                        ->end()
                        ->booleanNode('delayed_delivery_subscriber')
                            ->info('Whether delayed delivery subscriber is enabled')
                            ->example('true')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exception_subscriber')
                    ->info('Exception subscriber')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether exception subscriber is enabled')
                            ->example('true')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('exception_validator')
                            ->info('Callable that validates exception and decides if it must be processed')
                            ->example('service.exception.validator')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exception_sender')
                    ->info('Exception sender')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->info('Connection')
                            ->example('default')
                            ->defaultValue('default')
                        ->end()
                        ->arrayNode('exchange_options')
                            ->info('Exchange options')
                            ->example("{ name: 'exchange_name', type: topic }")
                            ->ignoreExtraKeys()
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('name')
                                    ->info('Name')
                                    ->example('default')
                                    ->defaultValue('default')
                                ->end()
                                ->scalarNode('type')
                                    ->info('Type')
                                    ->example('direct')
                                    ->defaultValue('topic')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('rpc_client')
                    ->info('RPC client')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether RPC client is used')
                            ->example('true')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('connection')
                            ->info('RPC client connection')
                            ->example('default')
                            ->defaultValue('default')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('rpc_server')
                    ->info('RPC server')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Whether RPC server is used')
                            ->example('true')
                            ->defaultFalse()
                        ->end()
                        ->scalarNode('connection')
                            ->info('RPC server connection')
                            ->example('default')
                            ->defaultValue('default')
                        ->end()
                        ->scalarNode('exchange')
                            ->info('RPC server exchange name')
                            ->example('rpc_exchange')
                            ->defaultValue('rpc_exchange')
                        ->end()
                        ->scalarNode('setup')
                            ->info('Setup (callable) to be run before a handler')
                            ->example('db.connection.refresh')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('handlers')
                            ->info('Command handlers')
                            ->example(json_encode(['App\Dto\Queue\GetUserCommand' => 'App\Rpc\UserHandler']))
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
