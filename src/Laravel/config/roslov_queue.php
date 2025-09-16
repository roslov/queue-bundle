<?php

return [
    'service_name' => env('ROSLOV_QUEUE_SERVICE_NAME', 'my_service'),
    'ssl_enabled' => env('ROSLOV_QUEUE_SSL_ENABLED', false),
    'logger' => env('ROSLOV_QUEUE_LOGGER', 'logger'),
    'entity_manager' => env('ROSLOV_QUEUE_ENTITY_MANAGER', null),
    'payload_mapping' => [
        'Error' => Roslov\QueueBundle\Dto\Error::class,
        'Trigger' => Roslov\QueueBundle\Dto\Trigger::class,
        'Response.Empty' => Roslov\QueueBundle\Dto\EmptyResponse::class,
        'Exception.Thrown' => Roslov\QueueBundle\Dto\ExceptionThrown::class,
    ],
    'event_processor' => [
        'enabled' => false,
        'instant_delivery' => true,
        'delayed_delivery_subscriber' => true,
    ],
    'exception_subscriber' => [
        'enabled' => false,
        'exception_validator' => null,
    ],
    'exception_sender' => [
        'connection' => 'default',
        'exchange_options' => [
            'name' => 'default',
            'type' => 'topic',
        ],
    ],
    'rpc_client' => [
        'enabled' => false,
        'connection' => 'default',
    ],
    'rpc_server' => [
        'enabled' => false,
        'connection' => 'default',
        'exchange' => 'rpc_exchange',
        'setup' => null,
        'handlers' => [],
    ],
];
