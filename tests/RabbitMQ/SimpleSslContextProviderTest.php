<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Tests\RabbitMQ;

use PHPUnit\Framework\TestCase;
use Roslov\QueueBundle\RabbitMQ\SimpleSslContextProvider;

/**
 * Tests SimpleSslContextProvider.
 */
final class SimpleSslContextProviderTest extends TestCase
{
    /**
     * Tests that getConnectionParameters returns ssl_context array when ssl is enabled.
     */
    public function testGetConnectionParametersWhenSslEnabled(): void
    {
        $provider = new SimpleSslContextProvider(true);

        $this->assertSame(
            [
                'ssl_context' => [
                    'ssl_on' => true,
                    'ssl_verify' => false,
                ],
            ],
            $provider->getConnectionParameters(),
        );
    }

    /**
     * Tests that getConnectionParameters returns empty array when ssl is disabled.
     */
    public function testGetConnectionParametersWhenSslDisabled(): void
    {
        $provider = new SimpleSslContextProvider(false);

        $this->assertSame([], $provider->getConnectionParameters());
    }
}
