---
name: bundle-testing
description: >-
  Write, structure, and run PHPUnit tests for Roslov Queue Bundle, including DI/container tests, compiler pass
  tests, and isolated tests for serializers, producers, the event processor, RPC, and exception events.
  Use when adding or changing any class under src/ or when increasing test coverage.
---

# Bundle Testing Workflow

Tests use **PHPUnit 12** (`phpunit.xml.dist`, suite `Queue bundle tests`, directory `./tests`). Run them only inside the
PHP 8.3 Docker container (see the `docker-verification` skill).

## Conventions

- Namespace `Roslov\QueueBundle\Tests\...`, path mirrors `src/` (`src/Rpc/Server.php` -> `tests/Rpc/ServerTest.php`).
- `final class ...Test extends PHPUnit\Framework\TestCase`; class and each test method have a doc block.
- Production class doc block links back to its test: `@see ServerTest` (with `use ...\Tests\Rpc\ServerTest;`).
- `declare(strict_types=1);`, `assertSame()`, explicit `use function ...;` imports where the codebase uses them.
- Data providers use `#[DataProvider('providerName')]` and are declared as `public static`.
- `createStub()` when no expectations are set; `createMock()` + `expects()` only to verify interactions
  (Rector `phpunitMockToStub` will flag mocks without expectations).
- PHPStan `level: max` covers `tests/`; annotate untyped values (`/** @var array<string, string> $mapping */`).
- PHPUnit fails on risky tests, warnings, and notices; every test must assert something.
- No network, database, or RabbitMQ access. Shared dummies go in `tests/Fixtures` (`final`, `Fixture: ...` doc).

Existing fixtures: `DummyDto` (`Dummy`), `DummyCommandDto` (`DummyCommand`, RPC handler `DummyHandler`),
`DummyConflictingDto` (duplicate type), `DummyCustomErrorDto` (overrides `Error`), `DummyHandler`.

---

## Recipes

### 1. DI / compiler pass tests

Build a `ContainerBuilder`, register only the definitions the code needs, run the extension/pass, and assert on
definition arguments (see `tests/RoslovQueueBundleTest.php`, `tests/DependencyInjection/`).

```php
$container = new ContainerBuilder();
(new RoslovQueueExtension())->load([['service_name' => 'test_service']], $container);

$this->assertSame('test_service', $container->getDefinition('roslov_queue.message_factory')->getArgument(1));
```

For `prepend()`, register a stub extension named `old_sound_rabbit_mq` (or check `getExtensionConfig()`), set the
`roslov_queue` config with `prependExtensionConfig()`, call `prepend()`, and assert on the prepended RabbitMQ config.

### 2. Serializer round-trip

```php
$payloadFactory = new PayloadFactory(Configuration::DEFAULT_PAYLOADS + ['Dummy' => DummyDto::class]);
$serializer = new MessagePayloadSerializer($payloadFactory, new MessageFactory($payloadFactory, 'test_service'));

$json = $serializer->serialize(new Trigger());
$this->assertSame(
    '{"type":"Trigger","source":"test_service","correlationId":"not-implemented","data":[]}',
    $json,
);
$this->assertInstanceOf(Trigger::class, $serializer->deserialize($json));
```

The `data: []` output above is verified behavior for field-less DTOs. Failure paths:
unknown `type` and invalid JSON -> `DeserializationException`; unmapped payload class -> `InvalidPayloadClassException`.

### 3. EventProcessor

`EventProcessor` is `final` with a **static** `$events` buffer, so isolate state between tests (e.g. call `flush()`
with a stubbed entity manager in `tearDown()`). Construct it with:

- `ProducerLocator` wrapping a PSR container stub returning a mocked `BaseProducer` for
  `old_sound_rabbit_mq.<name>_producer`;
- a stubbed/mocked `EntityManagerInterface` (and `Connection::isTransactionActive()`), or `null`;
- `new NullLogger()`, a real `MessagePayloadSerializer`, and a real `LogObfuscator`.

Cover: disabled (no calls), instant delivery (producer `send()` once, no EM calls), outbox inside/outside a
transaction, `sendImmediately` success and fallback, `flush()` failure -> `EventStorageFailedException`,
missing EM -> `LogicException`, `sendAll()` dry run and ordered send/remove loop.

### 4. RPC

- `Client`: mock `OldSound\RabbitMqBundle\RabbitMq\RpcClient`, stub `IdGeneratorInterface` with a fixed ID. Cover reply
  found, missing request ID, `AMQPTimeoutException` -> `TimeoutRpcClientException`, null client -> `LogicException`.
- `Server`: build `new AMQPMessage($json)`; cover disabled server, setup callable invoked, EM cleared, handler result
  serialized, unknown command, handler not implementing `HandlerInterface`.

### 5. Exception events

- `ExceptionSubscriber`: disabled, validator returning `false`, `HttpException` 404 skipped, 500 sent, console error.
- `ExceptionSender`: `ProducerFacade` and `EventProcessor` are both `final` and cannot be mocked. Build a real
  `EventProcessor` (enabled, instant delivery) whose `ProducerLocator` returns a mocked `BaseProducer` for
  `old_sound_rabbit_mq.event_exception_thrown_producer`, then assert on the JSON passed to `send()`.
  Back up and restore `$_SERVER['REQUEST_URI']`.

---

## Running Tests (inside the PHP 8.3 container)

```bash
composer test:unit
vendor/bin/phpunit tests/DependencyInjection
vendor/bin/phpunit --filter testProcessWithTaggedPayload
```

---

## Skill Maintenance & Self-Enforcement

When test tooling, fixtures, or testing conventions change, update this file
(`.agents/skills/bundle-testing/SKILL.md`) and `docs/testing.md` (including the coverage priorities list).
