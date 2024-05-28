<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\RabbitMQ;

use OldSound\RabbitMqBundle\Provider\ConnectionParametersProviderInterface;

/**
 * Class SimpleSslContextProvider — changes RabbitMQ params dynamically.
 *
 * It adds SSL context when needed.
 *
 * If is used for RabbitMQ bundle v2.11.2+. For previous versions, use {@link ConnectionParametersProvider}.
 */
final class SimpleSslContextProvider implements ConnectionParametersProviderInterface
{
    /**
     * @var bool Whether SSL connection is enabled
     */
    private bool $sslEnabled;

    /**
     * Constructor.
     *
     * @param bool $sslEnabled Whether SSL connection is enabled
     */
    public function __construct(bool $sslEnabled)
    {
        $this->sslEnabled = $sslEnabled;
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
        return $this->sslEnabled ? ['ssl_context' => $sslContext] : [];
    }
}
