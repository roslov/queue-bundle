<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\RabbitMQ;

use OldSound\RabbitMqBundle\Provider\ConnectionParametersProviderInterface;

/**
 * Class ConnectionParametersProvider — changes RabbitMQ params dynamically.
 *
 * It adds SSL context when needed.
 */
final class ConnectionParametersProvider implements ConnectionParametersProviderInterface
{
    /**
     * @var bool Whether SSL connection is enabled
     */
    private bool $sslEnabled;

    /**
     * @var bool Whether RabbitMQ bundle v1.x is used. It should be set to `false` fom RabbitMQ bundles v2+
     */
    private bool $legacyRabbitmqBundle;

    /**
     * Constructor.
     *
     * @param bool $sslEnabled Whether SSL connection is enabled
     * @param bool $legacyRabbitmqBundle Whether RabbitMQ bundle v1.x is used. It should be set to `false` fom RabbitMQ
     * bundles v2+
     */
    public function __construct(bool $sslEnabled, bool $legacyRabbitmqBundle)
    {
        $this->sslEnabled = $sslEnabled;
        $this->legacyRabbitmqBundle = $legacyRabbitmqBundle;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionParameters(): array
    {
        $sslContext = [
            'ssl_on' => true,
            'ssl_verify' => false,
        ];
        $contextParamName = $this->legacyRabbitmqBundle ? 'ssl_context' : 'context';
        return $this->sslEnabled ? [$contextParamName => stream_context_create(['ssl' => $sslContext])] : [];
    }
}
