Queue Bundle
============

This package provides the classes needed to work with RabbitMQ.

It is based on [RabbitMQ bundle](https://github.com/php-amqplib/RabbitMqBundle).


## Requirements

- PHP 7.4 or higher
- Symfony 3.4 or higher
- Doctrine bundle (optional)
- MySQL DB (optional)


## TODO

- [ ] RPC client: Allow multiple request calls
- [ ] Doctrine: Add automatic migrations
- [ ] Doctrine: Add automatic entity setup
- [ ] Tests: Add tests


## Installation and usage

### Default bundle configuration

The package could be installed with composer:

```shell
composer require roslov/queue-bundle
```

Then change the default settings by creating `config/packages/roslov_queue.yaml` with the content below.

```yaml
# config/packages/roslov_queue.yaml
roslov_queue:
  # Microservice name. This value will be used as a source of your published message
  service_name: my_service
  # Set this value to `true` if you’re using the SSL connection to RabbitMQ (for example, in AWS)
  ssl_enabled: false
  # PSR-3 logger service
  logger: logger
  # Entity manager service. If you do not produce messages, set it to `null` (`~`)
  entity_manager: doctrine.orm.default_entity_manager
  # Event processor
  event_processor:
    # Whether the event processor is enabled. If disabled, no events will be sent or saved
    enabled: false
    # Whether the event processor uses instant delivery. If disabled, the event processor is used as a transactional
    # outbox
    instant_delivery: true
    # Delayed delivery subscriber. If disabled, the events will be stored but not sent (useful for tests)
    delayed_delivery_subscriber: true
  # RPC client
  rpc_client:
    # Whether an RPC client should be created
    enabled: false
    # RabbitMQ connection
    connection: default
  # RPC server
  rpc_server:
    # Whether RPC server should be created
    enabled: false
    # RabbitMQ connection
    connection: default
    # Exchange name
    exchange: rpc_exchange
    # Setup callable. If you need to run some processes before running each handler (like DB connection refresh), add
    # the callable service here
    setup: ~
    # Handlers
    handlers: []
      # Put your handlers here:
      # App\Dto\Queue\GetUserCommand: App\Rpc\UserHandler
  # Message type to payload mapping. Extend this array with your payloads
  payload_mapping:
    Error: Roslov\QueueBundle\Dto\Error
    Trigger: Roslov\QueueBundle\Dto\Trigger
    Response.Empty: Roslov\QueueBundle\Dto\EmptyResponse
    Exception.Thrown: Roslov\QueueBundle\Dto\ExceptionThrown
    # Put your payloads here
  # By default, exception_subscriber is turned off
  exception_subscriber:
    # Whether exception subscriber should be enabled. If enabled, `exception_sender.exchange_options` is required
    enabled: false
    # Exception validator callable. If you need to check whether an exception subscriber should execute its code with
    # the given exception, add the callable service here. It must return `true` if the exception is OK and the
    # notification should be sent, or `false` if passed exception is not OK and should not be notified.
    # Check the example of the exception validator class below
    exception_validator: ~
  # Exception sender
  exception_sender:
    # RabbitMQ connection
    connection: default
    # Put exchange options here. This option is required if you either enabled `exception_subscriber` or use this sender
    # manually
    exchange_options: { name: 'exchange_name', type: topic }
```


### RabbitMQ configuration

This package also installs [RabbitMQ bundle](https://github.com/php-amqplib/RabbitMqBundle). So first, you need to
configure the RabbitMQ bundle. Follow its documentation. For example:

```yaml
# config/packages/old_sound_rabbit_mq.yaml
old_sound_rabbit_mq:
  # RabbitMQ connection config
  connections:
    default:
      url: '%env(RABBITMQ_URL)%'
      lazy: true
      connection_timeout: 5
      read_write_timeout: 60
      keepalive: false
      heartbeat: 30
      # Use this parameter only if you need to use SSL connection to RabbitMQ
      connection_parameters_provider: roslov_queue.rabbitmq.simple_ssl_context_provider
  # Producers (if used)
  producers:
    user_created:
      class: App\Producer\UserCreatedProducer
      connection: default
      exchange_options: { name: 'user', type: topic, auto_delete: false, durable: true }
      enable_logger: true
    # ...other producers
  # Multiple consumers
  multiple_consumers:
    main:
      connection: default
      exchange_options: { name: 'main', type: direct, auto_delete: false, durable: true }
      enable_logger: true
      queues:
        user-created:
          name: user_created
          routing_keys:
            - user-created
          callback: App\Consumer\UserCreatedConsumer
        # other consumers
```


### Consumers and producers

Create DTOs that will be used in consumers and producers, and add them to `roslov_queue.payload_mapping` (see examples).

Create a consumer that uses `Roslov\QueueBundle\Serializer\MessagePayloadSerializer` as a serializer:

```php
<?php

declare(strict_types=1);

namespace App\Consumer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Roslov\QueueBundle\Serializer\MessagePayloadSerializer;

final class UserCreatedConsumer implements ConsumerInterface
{
    public function __construct(private MessagePayloadSerializer $serializer)
    {
    }

    public function execute(AMQPMessage $msg): int
    {
        // Restore connections to DB if needed...
        // Refresh entity manager if used (`$this->em->clear()`)...

        $dto = $this->serializer->deserialize($msg->getBody());
        // `$dto` will be automatically detected based on a payload type.

        // Process DTO...

        return ConsumerInterface::MSG_ACK;
    }
}
```

Add your consumers to `old_sound_rabbit_mq.consumers` or `old_sound_rabbit_mq.multiple_consumers`.

Create a producer that extends `Roslov\QueueBundle\Producer\BaseProducer` and implement `getRoutingKey()`:

```php
<?php

declare(strict_types=1);

namespace App\Producer;

use Roslov\QueueBundle\Producer\BaseProducer;

final class UserCreatedProducer extends BaseProducer
{
    protected function getRoutingKey(): string
    {
        return 'user-created';
    }
}
```

Add your producers to `old_sound_rabbit_mq.producers`.

Create a producer facade to keep all producer calls in one place, by extending `BaseProducerFacade` and injecting
`EventProcessor`:

```php
<?php

declare(strict_types=1);

namespace App\Producer;

use App\Dto\Queue\UserCreated;
use Roslov\QueueBundle\Processor\EventProcessor;
use Roslov\QueueBundle\Producer\BaseProducerFacade;

/**
 * Keeps all calls to producers.
 */
final class ProducerFacade extends BaseProducerFacade
{
    public function __construct(
        EventProcessor $eventProcessor,
        // Inject other services
    ) {
        parent::__construct($eventProcessor);
    }

    public function sendUserCreatedEvent(int $userId): void
    {
        $payload = new UserCreated();
        $payload->setId($userId);
        $this->send('user_created', $payload);
    }
}
```

The events are stored in DB and are sent on kernel termination or after message consuming. So you have to create a DB
table for events. Currently, only Doctrine with MySQL is supported:

```sql
CREATE TABLE event (
    id bigint(20) AUTO_INCREMENT NOT NULL,
    microtime double(16,6) NOT NULL COMMENT 'Unix timestamp with microseconds',
    producer_name varchar(64) NOT NULL COMMENT 'Producer name',
    body varchar(4096) NOT NULL COMMENT 'Full message body',
    created_at timestamp NOT NULL DEFAULT current_timestamp COMMENT 'Creation timestamp',
    updated_at timestamp NOT NULL DEFAULT current_timestamp ON UPDATE current_timestamp
        COMMENT 'Update timestamp',
    PRIMARY KEY (id)
) COMMENT = 'Events (transactional outbox)';
```

And add the `Event` entity to the Doctrine config:

```yaml
# config/packages/doctrine.yaml
doctrine:
  orm:
    mappings:
      RoslovQueue:
        is_bundle: false
        type: attribute
        dir: '%kernel.project_dir%/vendor/roslov/queue-bundle/src/Entity'
        prefix: Roslov\QueueBundle\Entity
        alias: RoslovQueue
```

Now you can send an event by `$producerFacade->sendUserCreatedEvent(123)`.

The best way to use the event processor is to use in inside a transaction to comply with the Transactional Outbox
pattern.
So you have to call the producer facade somewhere in your code and then flush all events at the end of the transaction:

```php
$this->em->getConnection()->beginTransaction();
try {
    // Your code...
    $producerFacade->sendUserCreatedEvent(123); // Creating an event — the event will be stored in memory.
                                                // We cannot store it in DB right now because this code may be used in
                                                // Doctrine lifetime cycles.
    // Your code...
    $this->eventProcessor->flush(); // All events are being stored in DB.
                                    // This should be done right before committing. Otherwise, you may lose your events.
                                    // All events will be sent to RabbitMQ on kernel termination or on message consume.
    $this->em->getConnection()->commit();
} catch (Throwable $e) {
    $this->em->getConnection()->rollBack();
    throw $e;
}
```

Enable the event processor in `roslov_queue.event_processor.enabled` if you produce messages.

Note that by default, the transactional outbox support is disabled. To enable it, set
`roslov_queue.event_processor.instant_delivery` to `false`.

In some microservices, you do not need to use transactional outbox, so events can be sent immediately. In this case, set
`roslov_queue.event_processor.instant_delivery` to `true`, so both `BaseProducerFacade::send()` and
`EventProcessor::save()` will be sending the events instantly (without interim saving to DB). This is a default
behavior.

For automation tests, you can disable `roslov_queue.event_processor.delayed_delivery_subscriber`. In this case, the
events will be stored in DB but not sent. So you can test your DB whether events were created. Note that this will not
work if instant delivery is enabled — the events will be sent instantly.


### RPC servers and clients

If you need to use the remote procedure call (RPC), enable `roslov_queue.rpc_client.enabled` on your client service and
`roslov_queue.rpc_server.enabled` and `roslov_queue.rpc_server.exchange` on your server service:

```yaml
# config/packages/roslov_queue.yaml
roslov_queue:
  rpc_client:
    enabled: true
  rpc_server:
    enabled: true
    exchange: rpc_exchange
```

The example of an RPC client use:

```php
<?php

declare(strict_types=1);

namespace App\Queue;

use App\Dto\Queue\GetUserCommand;
use App\Dto\Queue\User;
use Psr\Log\LoggerInterface;
use Roslov\QueueBundle\Dto\Error;
use Roslov\QueueBundle\Exception\UnknownErrorException;
use Roslov\QueueBundle\Rpc\ClientInterface;

final class UserProvider
{
    private const EXCHANGE_NAME = 'rpc.main';

    private const USER_NOT_FOUND = 'UserNotFound';

    public function __construct(private ClientInterface $client, private LoggerInterface $logger)
    {
    }

    public function getUser(int $id): ?User
    {
        $command = new GetUserCommand();
        $command->setId($id);

        /** @var User|Error $user */
        $user = $this->client->call($command, self::EXCHANGE_NAME);

        if ($user instanceof User) {
            $this->logger->info("The details for the user with id \"$id\" have been received.");
            return $user;
        }
        if ($user instanceof Error && $user->getType() === self::USER_NOT_FOUND) {
            $this->logger->info("The user with id \"$id\" does not exist on the remote server.");
            return null;
        }
        throw new UnknownErrorException('Unknown error happened.');
    }
}
```

For an RPC server, add handlers that process commands and return results:

```yaml
# config/packages/roslov_queue.yaml
roslov_queue:
  rpc_server:
    handlers:
      App\Dto\Queue\GetUserCommand: App\Rpc\UserHandler
      # Other handlers...
```

The example of an RPC server handler:

```php
<?php

declare(strict_types=1);

namespace App\Rpc;

use App\Dto\Queue\GetUserCommand;
use InvalidArgumentException;
use Roslov\QueueBundle\Dto\Error;
use Roslov\QueueBundle\Rpc\HandlerInterface;

final class UserHandler implements HandlerInterface
{
    private const USER_NOT_FOUND = 'UserNotFound';

    public function handle(object $command): object
    {
        if (!$command instanceof GetUserCommand) {
            throw new InvalidArgumentException(sprintf(
                'Command "%s" is not supported. The handler supports "%s" only.',
                $command::class,
                GetUserCommand::class
            ));
        }

        // Search for a user
        $user = $this->findUser($command->getId()); // Your code for getting a user

        if ($user === null) {
            $error = new Error();
            $error->setType(self::USER_NOT_FOUND);
            $error->setMessage('User not found.');
            return $error;
        }
        return $user;
    }
}
```

To run the RPC server, use:

```shell
bin/console rabbitmq:rpc-server roslov_queue
```


### Exception events

This bundle allows automatic sending of events about thrown exceptions.

Note that by default, the `exception_subscriber` is disabled. To enable it, set
`roslov_queue.exception_subscriber.enabled` to `true`.

The exception subscriber uses the routing key `exception-thrown`.

Example of exception validator class, that can be passed to the `roslov_queue.exception_validator` configuration:
```php
<?php
final class ExceptionValidator
{
    /**
     * Returns `true` if notification about an exception SHOULD BE sent.
     *
     * In this case, we notify about all exceptions except `UserNotFoundException`.
     *
     * @param \Throwable $exception The exception that must be validated
     * @return bool Validation result
     */
    public function __invoke(\Throwable $exception): bool
    {
        return !$exception instanceof \App\Exception\UserNotFoundException;
    }
}
```

If you want to send an exception event manually, use
`\Roslov\QueueBundle\Sender\ExceptionSender::sendExceptionThrownEvent()`.


### Resending the message

In case something happened, and you need to resend the same message again to the same queue, use
`return ConsumerInterface::MSG_SINGLE_NACK_REQUEUE;` instead of `return ConsumerInterface::MSG_ACK;` in your consumer.


## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Code style analysis

The code style is analyzed with [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and
[PSR-12 Ext coding standard](https://github.com/roslov/psr12ext). To run code style analysis:

```shell
./vendor/bin/phpcs --extensions=php --colors --standard=PSR12Ext --ignore=vendor/* -p -s .
```

