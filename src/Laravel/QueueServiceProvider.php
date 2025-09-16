<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Laravel;

use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Roslov\QueueBundle\Factory\MessageFactory;
use Roslov\QueueBundle\Factory\PayloadFactory;
use Roslov\QueueBundle\Generator\IdGenerator;
use Roslov\QueueBundle\Generator\IdGeneratorInterface;
use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Roslov\QueueBundle\Producer\ProducerLocator;
use Roslov\QueueBundle\Sender\ExceptionSender;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;
use Roslov\LogObfuscator\LogObfuscator;

/**
 * Laravel service provider for queue bundle.
 */
final class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(PayloadFactory::class, function (): PayloadFactory {
            return new PayloadFactory([
                'Error' => \Roslov\QueueBundle\Dto\Error::class,
                'Trigger' => \Roslov\QueueBundle\Dto\Trigger::class,
                'Response.Empty' => \Roslov\QueueBundle\Dto\EmptyResponse::class,
                'Exception.Thrown' => \Roslov\QueueBundle\Dto\ExceptionThrown::class,
            ]);
        });

        $this->app->singleton(MessageFactory::class, function (ContainerInterface $app): MessageFactory {
            return new MessageFactory(
                $app->get(PayloadFactory::class),
                config('app.name')
            );
        });

        $this->app->singleton(MessagePayloadSerializer::class, function (ContainerInterface $app): MessagePayloadSerializer {
            return new MessagePayloadSerializer(
                $app->get(PayloadFactory::class),
                $app->get(MessageFactory::class)
            );
        });

        $this->app->singleton(IdGeneratorInterface::class, IdGenerator::class);

        $this->app->singleton(ProducerLocator::class, function (ContainerInterface $app): ProducerLocator {
            return new ProducerLocator($app);
        });

        $this->app->singleton(EventProcessor::class, function (ContainerInterface $app): EventProcessor {
            return new EventProcessor(
                true,
                true,
                $app->get(ProducerLocator::class),
                null,
                $app->get(LoggerInterface::class),
                $app->get(MessagePayloadSerializer::class),
                new LogObfuscator()
            );
        });

        $this->app->singleton(ProducerFacade::class, function (ContainerInterface $app): ProducerFacade {
            return new ProducerFacade($app->get(EventProcessor::class));
        });

        $this->app->singleton(ExceptionShortener::class, ExceptionShortener::class);

        $this->app->singleton(ExceptionSender::class, function (ContainerInterface $app): ExceptionSender {
            return new ExceptionSender(
                $app->get(ProducerFacade::class),
                $app->get(ExceptionShortener::class),
                config('app.name')
            );
        });
    }
}

