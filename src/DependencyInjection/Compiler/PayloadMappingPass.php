<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\DependencyInjection\Compiler;

use LogicException;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function in_array;
use function is_string;
use function sprintf;

/**
 * Registers message payloads and RPC handlers configured via attributes or configuration.
 */
final class PayloadMappingPass implements CompilerPassInterface
{
    /**
     * Parameter name for explicitly configured payload types
     */
    public const CONFIGURED_PAYLOAD_TYPES_PARAM = 'roslov_queue.configured_payload_types';

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('roslov_queue.payload_factory')) {
            return;
        }

        $payloadMapping = $this->getInitialPayloadMapping($container);
        $configuredTypes = $this->getConfiguredPayloadTypes($container);
        $handlers = $this->getInitialHandlers($container);

        $this->processTaggedServices(
            $container,
            $payloadMapping,
            $handlers,
            $configuredTypes,
        );

        $this->updateDefinitions($container, $payloadMapping, $handlers);
        $this->cleanupParameters($container);
    }

    /**
     * Returns the initial payload mapping with defaults.
     *
     * @param ContainerBuilder $container Container builder
     *
     * @return array<string, string> Payload mapping
     */
    private function getInitialPayloadMapping(ContainerBuilder $container): array
    {
        $payloadFactoryDefinition = $container->getDefinition('roslov_queue.payload_factory');
        /** @var array<string, string> $payloadMapping */
        $payloadMapping = $payloadFactoryDefinition->getArgument(0);

        foreach (Configuration::DEFAULT_PAYLOADS as $type => $class) {
            $payloadMapping[$type] ??= $class;
        }

        return $payloadMapping;
    }

    /**
     * Returns explicitly configured payload types from YAML.
     *
     * @param ContainerBuilder $container Container builder
     *
     * @return list<string> Configured payload types
     */
    private function getConfiguredPayloadTypes(ContainerBuilder $container): array
    {
        if (!$container->hasParameter(self::CONFIGURED_PAYLOAD_TYPES_PARAM)) {
            return [];
        }

        /** @var list<string> $configuredPayloadTypes */
        $configuredPayloadTypes = (array) $container->getParameter(self::CONFIGURED_PAYLOAD_TYPES_PARAM);

        return $configuredPayloadTypes;
    }

    /**
     * Returns initial RPC handlers.
     *
     * @param ContainerBuilder $container Container builder
     *
     * @return array<string, Reference> RPC handlers
     */
    private function getInitialHandlers(ContainerBuilder $container): array
    {
        if (!$container->hasDefinition('roslov_queue.rpc.server_callback')) {
            return [];
        }

        $rpcServerDefinition = $container->getDefinition('roslov_queue.rpc.server_callback');

        /** @var array<string, Reference> $handlers */
        $handlers = $rpcServerDefinition->getArgument(3);

        return $handlers;
    }

    /**
     * Processes all tagged payload services.
     *
     * @param ContainerBuilder $container Container builder
     * @param array<string, string> $payloadMapping Payload mapping
     * @param array<string, Reference> $handlers RPC handlers
     * @param list<string> $configuredPayloadTypes Configured payload types
     */
    private function processTaggedServices(
        ContainerBuilder $container,
        array &$payloadMapping,
        array &$handlers,
        array $configuredPayloadTypes,
    ): void {
        $taggedServices = $container->findTaggedServiceIds('roslov_queue.payload');
        foreach ($taggedServices as $id => $tags) {
            $definition = $container->getDefinition($id);
            $className = $definition->getClass() ?? $id;

            $this->processTags(
                $tags,
                $className,
                $payloadMapping,
                $handlers,
                $configuredPayloadTypes,
            );

            $definition->clearTag('roslov_queue.payload');
        }
    }

    /**
     * Processes tags for a single service definition.
     *
     * @param array<array<string, mixed>> $tags Tags
     * @param string $className Class name
     * @param array<string, string> $payloadMapping Payload mapping
     * @param array<string, Reference> $handlers RPC handlers
     * @param list<string> $configuredPayloadTypes Configured payload types
     */
    private function processTags(
        array $tags,
        string $className,
        array &$payloadMapping,
        array &$handlers,
        array $configuredPayloadTypes,
    ): void {
        foreach ($tags as $tag) {
            $this->processSingleTag(
                $tag,
                $className,
                $payloadMapping,
                $handlers,
                $configuredPayloadTypes,
            );
        }
    }

    /**
     * Processes a single payload tag.
     *
     * @param array<string, mixed> $tag Tag
     * @param string $className Class name
     * @param array<string, string> $payloadMapping Payload mapping
     * @param array<string, Reference> $handlers RPC handlers
     * @param list<string> $configuredPayloadTypes Configured payload types
     */
    private function processSingleTag(
        array $tag,
        string $className,
        array &$payloadMapping,
        array &$handlers,
        array $configuredPayloadTypes,
    ): void {
        $type = $tag['type'] ?? null;
        $payloadClass = $tag['class'] ?? $className;
        $rpcHandler = $tag['rpc_handler'] ?? null;

        if (is_string($type) && is_string($payloadClass)) {
            $this->registerPayload($payloadMapping, $type, $payloadClass, $configuredPayloadTypes);
        }

        if (is_string($payloadClass) && is_string($rpcHandler)) {
            $this->registerHandler($handlers, $payloadClass, $rpcHandler);
        }
    }

    /**
     * Registers an RPC handler for a payload class.
     *
     * @param array<string, Reference> $handlers RPC handlers
     * @param string $payloadClass Payload class
     * @param string $rpcHandler RPC handler
     */
    private function registerHandler(array &$handlers, string $payloadClass, string $rpcHandler): void
    {
        if (!isset($handlers[$payloadClass])) {
            $handlers[$payloadClass] = new Reference($rpcHandler);
        }
    }

    /**
     * Registers a payload class for a message type.
     *
     * @param array<string, string> $payloadMapping Type-to-payload mapping
     * @param string $type Message type
     * @param string $payloadClass Payload class name
     * @param list<string> $configuredPayloadTypes Payload types explicitly configured in YAML
     */
    private function registerPayload(
        array &$payloadMapping,
        string $type,
        string $payloadClass,
        array $configuredPayloadTypes,
    ): void {
        if (in_array($type, $configuredPayloadTypes, true)) {
            return;
        }

        $existingClass = $payloadMapping[$type] ?? null;
        if ($existingClass === null || $this->isDefaultPayload($type, $existingClass)) {
            $payloadMapping[$type] = $payloadClass;

            return;
        }

        if ($existingClass !== $payloadClass) {
            throw new LogicException(sprintf(
                'Duplicate message type "%s" declared for classes "%s" and "%s".',
                $type,
                $existingClass,
                $payloadClass,
            ));
        }
    }

    /**
     * Checks if a type and class match a bundle default payload.
     *
     * @param string $type Message type
     * @param string $class Class name
     *
     * @return bool Whether it matches a default payload
     */
    private function isDefaultPayload(string $type, string $class): bool
    {
        return isset(Configuration::DEFAULT_PAYLOADS[$type])
        && Configuration::DEFAULT_PAYLOADS[$type] === $class;
    }

    /**
     * Updates service definitions with compiled mappings and handlers.
     *
     * @param ContainerBuilder $container Container builder
     * @param array<string, string> $payloadMapping Payload mapping
     * @param array<string, Reference> $handlers RPC handlers
     */
    private function updateDefinitions(
        ContainerBuilder $container,
        array $payloadMapping,
        array $handlers,
    ): void {
        $container->getDefinition('roslov_queue.payload_factory')
            ->replaceArgument(0, $payloadMapping);

        if ($container->hasDefinition('roslov_queue.rpc.server_callback')) {
            $container->getDefinition('roslov_queue.rpc.server_callback')
                ->replaceArgument(3, $handlers);
        }
    }

    /**
     * Removes temporary container parameters.
     *
     * @param ContainerBuilder $container Container builder
     */
    private function cleanupParameters(ContainerBuilder $container): void
    {
        if ($container->hasParameter(self::CONFIGURED_PAYLOAD_TYPES_PARAM)) {
            $container->getParameterBag()->remove(self::CONFIGURED_PAYLOAD_TYPES_PARAM);
        }
    }
}
