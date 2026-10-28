# Requirements

## Product summary

Roslov Queue Bundle (`roslov/queue-bundle`) is a Symfony bundle that standardizes RabbitMQ messaging between PHP
microservices. It wraps `php-amqplib/rabbitmq-bundle` and adds a common message envelope, DTO payload mapping,
reliable event publishing (instant delivery or transactional outbox), RPC calls, and automatic exception events.

The bundle is distributed via Packagist and is consumed by downstream services (for example, the SlotCatalog Live
`api`, `cron`, `mailer`, and `alerts` services), each of which configures it via `config/packages/roslov_queue.yaml`.

## Supported platforms

| Component               | Supported versions                                             | Source              |
|-------------------------|----------------------------------------------------------------|---------------------|
| PHP                     | `^8.3` (CI matrix: 8.3, 8.4, 8.5; 8.6 is pending)              | `composer.json`, CI |
| PHP extensions          | `json`, `mbstring`, `sockets`                                  | `composer.json`     |
| Symfony                 | `^6.0 \|\| ^7.0 \|\| ^8.0` (config, DI, framework, serializer) | `composer.json`     |
| RabbitMQ bundle         | `php-amqplib/rabbitmq-bundle ^2.12`                            | `composer.json`     |
| Doctrine ORM            | `^3.2 \|\| ^4.0` (required by Composer)                        | `composer.json`     |
| Database for the outbox | MySQL (only supported option)                                  | `README.md`         |

## Functional requirements

### 1. Message envelope

- Every published payload is wrapped in `Roslov\QueueBundle\Dto\Message` and serialized to JSON:
  `{"type": "...", "source": "...", "correlationId": "...", "data": {...}}`.
- `type` is resolved from the payload mapping; `source` is `roslov_queue.service_name`.
- `correlationId` is currently always `not-implemented` (reserved for future use).
- Deserialization resolves the DTO class from `type`; unknown types or malformed JSON raise
  `DeserializationException` (the JSON in the message is truncated to 1000 characters).

### 2. Payload mapping

- Default payloads are always registered: `Error`, `Trigger`, `Response.Empty`, `Exception.Thrown`.
- Custom payloads are registered with `#[AsQueuePayload('Type')]` on the DTO class (requires the DTO to be a
  registered, autoconfigured service) or with `roslov_queue.payload_mapping`.
- Explicit YAML mapping wins over attribute mapping for the same type; attributes may override defaults.
- Two different classes declaring the same type via attributes raise a `LogicException` at container compile time.

### 3. Producers and event processor

- Producers extend `BaseProducer` and implement `getRoutingKey()`; they publish with `application/json`.
- Applications send events through a facade extending `BaseProducerFacade::send($producerName, $payload)`.
- `ProducerLocator` resolves producers as `old_sound_rabbit_mq.<producerName>_producer`.
- `event_processor.enabled: false` (default) makes `save()`, `flush()`, and `sendAll()` no-ops.
- `event_processor.instant_delivery: true` (default) publishes immediately without database storage.
- `event_processor.instant_delivery: false` enables the transactional outbox:
  - inside an active DB transaction, events are buffered in memory until `EventProcessor::flush()`;
  - outside a transaction, events are persisted immediately;
  - `EventSenderSubscriber` calls `sendAll()` on `kernel.terminate`, AMQP `ON_CONSUME`, and
    `AFTER_PROCESSING_MESSAGE`, sending stored events in `microtime` order and deleting each after publishing;
  - `delayed_delivery_subscriber: false` stores events but never sends them (useful for tests).
- `sendImmediately: true` attempts direct publishing first and falls back to storage on failure.
- Storage failures raise `EventStorageFailedException`.

### 4. RPC

- `rpc_client.enabled` prepends an `old_sound_rabbit_mq.rpc_clients.roslov_queue` client.
- `ClientInterface::call($command, $exchangeName, $timeout = 15)` returns the deserialized reply, raising
  `TimeoutRpcClientException` or `RequestIdNotFoundRpcClientException`.
- `rpc_server.enabled` prepends an `old_sound_rabbit_mq.rpc_servers.roslov_queue` server (prefetch 1) with
  `roslov_queue.rpc.server_callback` as the callback; run it with `bin/console rabbitmq:rpc-server roslov_queue`.
- The server runs the optional `setup` callable, clears the entity manager, dispatches to the handler registered for
  the command class (`HandlerInterface`), and serializes the result. Unknown commands raise
  `UnsupportedCommandRpcServerException`; invalid handlers raise `InvalidHandlerRpcServerException`.

### 5. Exception events

- The bundle always prepends an `event_exception_thrown` producer (class `ExceptionThrownProducer`, routing key
  `exception-thrown`) using `exception_sender.connection` and `exception_sender.exchange_options`.
- `exception_subscriber.enabled: true` sends `Exception.Thrown` for `kernel.exception` (skipping `HttpException`s other
  than 500) and `console.error`, unless the optional `exception_validator` callable returns `false`.
- Exception events are sent with `sendImmediately: true`; messages and URIs are shortened to 400 characters and
  traces to 2700 characters (JSON-encoded length).

### 6. SSL

- `ssl_enabled` configures `roslov_queue.rabbitmq.simple_ssl_context_provider`, which consumers reference via
  `connection_parameters_provider` in the RabbitMQ bundle configuration.

## Configuration reference

The full, commented configuration lives in `README.md` ("Default bundle configuration") and must be kept in sync with
`src/DependencyInjection/Configuration.php`.

## Known inconsistencies and open items

These items were found during documentation and are intentionally not changed without a decision:

- `README.md` refers to `roslov_queue.exception_validator`, but the actual key is
  `roslov_queue.exception_subscriber.exception_validator`.
- `README.md` lists Doctrine as optional, but `doctrine/orm` is a hard Composer requirement.
- `psalm.xml` exists, but Psalm is not installed or run (`vimeo/psalm` is not in `require-dev`).
- `composer test:integration` is a placeholder; no integration tests exist.
- PHP 8.6 is commented out in `.github/workflows/*.yml` pending Composer Normalizer compatibility (TODO).
- The `.github/workflows/static.yml` job is named `mutation`, but it runs static analysis.
- `ExceptionSubscriber::getException()` names `ExceptionEvent` twice in its error message.
