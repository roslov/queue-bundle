<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\DependencyInjection\Compiler\PayloadMappingPass;
use Roslov\QueueBundle\DependencyInjection\Configuration;
use Roslov\QueueBundle\DependencyInjection\RoslovQueueExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Tests the RoslovQueueExtension.
 */
final class RoslovQueueExtensionTest extends TestCase
{
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
}
