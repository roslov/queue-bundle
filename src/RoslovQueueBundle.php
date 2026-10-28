<?php

declare(strict_types=1);

namespace Roslov\QueueBundle;

use ReflectionClass;
use Reflector;
use Roslov\QueueBundle\Attribute\AsQueuePayload;
use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\DependencyInjection\RoslovQueueExtension;
use Roslov\QueueBundle\Tests\RoslovQueueBundleTest;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

/**
 * Queue bundle.
 *
 * @see RoslovQueueBundleTest
 */
final class RoslovQueueBundle extends Bundle
{
    /**
     * @inheritDoc
     */
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * @inheritDoc
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new RoslovQueueExtension();
    }

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->registerAttributeForAutoconfiguration(
            AsQueuePayload::class,
            static function (
                ChildDefinition $definition,
                AsQueuePayload $attribute,
                Reflector $reflector,
            ): void {
                if (!$reflector instanceof ReflectionClass) {
                    return;
                }

                $tagAttributes = [
                    'type' => $attribute->getType(),
                    'class' => $reflector->getName(),
                ];
                if ($attribute->getRpcHandler() !== null) {
                    $tagAttributes['rpc_handler'] = $attribute->getRpcHandler();
                }

                $definition->addTag('roslov_queue.payload', $tagAttributes);
            },
        );

        $container->addCompilerPass(new PayloadMappingPass());
    }
}
