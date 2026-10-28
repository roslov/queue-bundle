---
name: queue-messaging-and-rpc
description: >-
  Implement and maintain the message envelope, payload DTOs, AsQueuePayload mapping, MessagePayloadSerializer,
  producers, the event processor (instant delivery and transactional outbox), RPC client/server, and
  Exception.Thrown events in Roslov Queue Bundle. Use when changing runtime messaging behavior or default payloads.
---

# Queue Messaging and RPC Workflow

## Runtime Components

- **Envelope**: `src/Dto/Message.php`, `src/Dto/MessageInterface.php`
  Fields: `type`, `source`, `correlationId`, `data`.
- **Default payloads**: `src/Dto/{Error,Trigger,EmptyResponse,ExceptionThrown}.php`
  Types: `Error`, `Trigger`, `Response.Empty`, `Exception.Thrown`.
- **Type <-> class mapping**: `src/Factory/PayloadFactory.php`
  Strict allow-list mapping message type to DTO class.
- **Envelope factory**: `src/Factory/MessageFactory.php`
  Sets `source` and `correlationId` (`not-implemented`).
- **Serializer**: `src/Serializer/MessagePayloadSerializer.php`
  Uses Symfony `Serializer` with `ObjectNormalizer` and `JsonEncoder`.
- **Producers**: `src/Producer/BaseProducer.php`, `BaseProducerFacade.php`, `ProducerLocator.php`
  Base classes and locator for application event dispatching.
- **Event processor**: `src/Processor/EventProcessor.php`, `src/Entity/Event.php`
  Supports instant delivery and transactional outbox.
- **Outbox flushing**: `src/Subscriber/EventSenderSubscriber.php`
  Flushes events on `kernel.terminate` and AMQP consume events.
- **RPC**: `src/Rpc/{Client,ClientInterface,Server,HandlerInterface}.php`
  Default RPC timeout is 15 seconds.
- **Exception events**: `src/Subscriber/ExceptionSubscriber.php`, `src/Sender/ExceptionSender.php`,
  `src/Helper/ExceptionShortener.php`, `src/Producer/ProducerFacade.php`, `ExceptionThrownProducer.php`.
  Publishes `Exception.Thrown` with routing key `exception-thrown`.

## Compatibility Rules

- The JSON envelope is a cross-service contract. Never rename or remove fields or default message types; consumers in
  other services (with possibly older bundle versions) must still deserialize messages.
- Adding optional DTO fields is compatible; removing or retyping fields is breaking.
- `BaseProducer::getRoutingKey()` and `BaseProducerFacade::send(string, object, bool)` signatures are public API.
- `ProducerLocator` service naming (`old_sound_rabbit_mq.<name>_producer`) must not change.
- The outbox `event` table schema in `README.md` must match `src/Entity/Event.php`.
- Bundle exceptions extend `Roslov\QueueBundle\Exception\Exception`; add new ones under `src/Exception`.

---

## Step-by-Step: Adding a Default Payload

Default payloads are shared by all consumers, so add one only on explicit request.

1. Create `src/Dto/<Name>.php`: `final` class, private typed properties, documented getters/setters, and
   `#[AsQueuePayload('<Type>')]`.
2. Add it to `Configuration::DEFAULT_PAYLOADS` and to the `roslov_queue.payload_factory` argument in `services.yaml`.
3. Update tests that assert `DEFAULT_PAYLOADS` and the README lists of default payloads.

## Step-by-Step: Changing EventProcessor Behavior

1. Preserve the decision order in `save()`: disabled -> instant delivery -> `sendImmediately` attempt -> store.
2. Keep the static buffer semantics: buffer while a transaction is active; persist on `flush()`; clear only after a
   successful flush.
3. Wrap storage errors in `EventStorageFailedException` with the previous exception.
4. Log bodies on `info`+ only through `LogObfuscator`.
5. Add tests for each branch (see `bundle-testing` skill, recipe 3) and update the publishing flow in
   `docs/architecture.md`.

## Step-by-Step: Changing RPC

1. `Client::call()` must always correlate replies by the generated request ID and map broker timeouts to
   `TimeoutRpcClientException`.
2. `Server` must run `setup`, clear the entity manager, and dispatch strictly by `$command::class`.
3. Handlers return a payload DTO or `Roslov\QueueBundle\Dto\Error` (`type`, `message`) for domain errors.
4. Update README RPC examples and `docs/architecture.md` ("RPC flow").

## Step-by-Step: Changing Exception Events

1. Keep size bounds (`ExceptionShortener`: message 400, trace 2700, measured on JSON-encoded length).
2. Keep filtering: `enabled`, optional validator callable, `HttpException` only when status is 500.
3. Exception events are sent with `sendImmediately: true` so they are not lost with a rolled-back transaction.
4. Update `docs/security.md` if new data is added to `ExceptionThrown`.

---

## Verification (inside the PHP 8.3 container)

```bash
composer test:static
composer test:unit
```

---

## Skill Maintenance & Self-Enforcement

When the envelope, payloads, producers, event processor, RPC, or exception event behavior changes, update this file
(`.agents/skills/queue-messaging-and-rpc/SKILL.md`), `README.md`, `docs/requirements.md`, and `docs/architecture.md`.
