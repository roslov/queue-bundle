<?php

declare(strict_types=1);

namespace Roslov\QueueBundle\Laravel;

use Illuminate\Support\ServiceProvider;
use Roslov\QueueBundle\Helper\ExceptionShortener;
use Roslov\QueueBundle\Producer\ProducerFacade;
use Roslov\QueueBundle\Sender\ExceptionSender;

/**
 * Laravel service provider for the queue package.
 */
class QueueServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/roslov_queue.php', 'roslov_queue');

        $this->app->singleton(ExceptionShortener::class, function () {
            return new ExceptionShortener();
        });

        $this->app->singleton(ExceptionSender::class, function ($app) {
            return new ExceptionSender(
                $app->make(ProducerFacade::class),
                $app->make(ExceptionShortener::class),
                config('roslov_queue.service_name', 'service')
            );
        });
    }

    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/roslov_queue.php' => config_path('roslov_queue.php'),
        ], 'config');
    }
}
