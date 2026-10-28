<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\RabbitMQ;

use OldSound\RabbitMqBundle\Provider\ConnectionParametersProviderInterface;

/**
 * Class SimpleSslContextProvider — changes RabbitMQ params dynamically.
 *
 * It adds SSL context when needed.
 */
final readonly class SimpleSslContextProvider implements ConnectionParametersProviderInterface
{
    /**
     * Constructor.
     *
     * @param bool $sslEnabled Whether SSL connection is enabled
     */
    public function __construct(private bool $sslEnabled)
    {
    }

    /**
     * @inheritDoc
     * @phpstan-ignore missingType.iterableValue
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
