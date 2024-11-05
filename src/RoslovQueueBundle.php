<?php

declare(strict_types=1);

namespace Roslov\QueueBundle;

use Roslov\QueueBundle\DependencyInjection\RoslovQueueExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

use function dirname;

/**
 * Queue bundle.
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
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new RoslovQueueExtension();
    }
}
