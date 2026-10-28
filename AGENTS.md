# AGENTS.md

## Purpose

This repository contains **Roslov Queue Bundle** (`roslov/queue-bundle`), a reusable, MIT-licensed Symfony bundle
published on Packagist. It provides the classes needed to exchange messages between microservices over RabbitMQ on
top of `php-amqplib/rabbitmq-bundle`:

- a JSON message envelope (`type`, `source`, `correlationId`, `data`) and DTO payload mapping;
- producers, a producer facade, and an event processor (instant delivery or transactional outbox via Doctrine);
- an RPC client and RPC server;
- automatic `Exception.Thrown` events for HTTP and console exceptions.

The bundle is a library, not an application. It is consumed by downstream services (for example, the SlotCatalog Live
`api`, `cron`, `mailer`, and `alerts` services). Every change to public classes, configuration keys, service IDs, or
the message format can break those consumers.

This file is mandatory guidance for all automated agents and human contributors.

## Required reading

Before changing code, read:

- `README.md` (public user documentation)
- `docs/requirements.md`
- `docs/architecture.md`
- `docs/security.md`
- `docs/testing.md`
- `docs/acceptance-criteria.md`

## Scope discipline

- Implement only the requested task.
- Do not silently resolve product ambiguity with a broad assumption.
- Record a material unresolved decision in the task report.
- Keep pull requests narrow and reviewable.
- Do not mix unrelated refactoring with functional changes.
- Do not upgrade or add dependencies during unrelated work.
- `composer.lock` is intentionally git-ignored (this is a library); do not commit it.
- Do not edit `.idea/` files.
- Agents are not allowed to install anything on the host machine or WSL and must use Docker containers only.
- Never commit or push changes to git without an explicit user request.

## Execution environment

All project-related console commands (`composer`, `php`, `phpunit`, `phpstan`, `rector`, `phpcs`, ...) must run
inside a **PHP 8.3** Docker container (the lowest supported PHP version). Use Docker Compose (`compose.yaml`):

```bash
docker compose run --rm app composer test:static
```

See `.agents/skills/docker-verification/SKILL.md` for details.

## Architectural boundaries

Source code lives under `src/` (namespace `Roslov\QueueBundle\`):

- `src/RoslovQueueBundle.php`: bundle class; registers `#[AsQueuePayload]` autoconfiguration and `PayloadMappingPass`.
- `src/DependencyInjection`: `Configuration` (config tree), `RoslovQueueExtension` (load + prepend RabbitMQ config),
  `Compiler/PayloadMappingPass` (merges attribute-based payloads and RPC handlers).
- `src/Resources/config/services.yaml`: service definitions (`roslov_queue.*`) and public aliases.
- `src/Attribute`: `AsQueuePayload` attribute.
- `src/Dto`: message envelope (`Message`, `MessageInterface`) and default payloads (`Error`, `Trigger`,
  `EmptyResponse`, `ExceptionThrown`).
- `src/Factory`, `src/Serializer`: payload/message factories and `MessagePayloadSerializer`.
- `src/Producer`: `BaseProducer`, `BaseProducerFacade`, `ProducerLocator`, internal `ProducerFacade`,
  `ExceptionThrownProducer`.
- `src/Processor`: `EventProcessor` (instant delivery / transactional outbox).
- `src/Entity`, `src/Repository`: Doctrine `Event` outbox entity and repository.
- `src/Rpc`: RPC `Client`, `ClientInterface`, `Server`, `HandlerInterface`.
- `src/Subscriber`: `EventSenderSubscriber` (flushes outbox) and `ExceptionSubscriber`.
- `src/Sender`, `src/Helper`: `ExceptionSender` and `ExceptionShortener`.
- `src/Generator`: RPC request ID generator.
- `src/RabbitMQ`: `SimpleSslContextProvider`.
- `src/Exception`: bundle exceptions (all extend `Roslov\QueueBundle\Exception\Exception`).
- `tests/`: PHPUnit tests mirroring `src/`; shared test doubles in `tests/Fixtures`.

## Backward compatibility rules

This bundle is a public package with semantic versioning. Treat the following as public API:

- public/protected methods of non-`final` extension points (`BaseProducer`, `BaseProducerFacade`) and interfaces
  (`ClientInterface`, `HandlerInterface`, `ProducerInterface`, `MessageInterface`, `IdGeneratorInterface`);
- the `roslov_queue` configuration tree (keys, defaults, and semantics);
- service IDs and aliases in `services.yaml`, the `roslov_queue.payload` tag, and the prepended RabbitMQ names
  (`roslov_queue` RPC client/server, `event_exception_thrown` producer, `old_sound_rabbit_mq.<name>_producer` lookup);
- the JSON envelope format and the default message types (`Error`, `Trigger`, `Response.Empty`, `Exception.Thrown`);
- the `event` table schema documented in `README.md`.

Do not introduce a breaking change without an explicit user request. If one is requested, document it in the task
report and in `README.md`.

## Coding rules

- Every PHP file declares `declare(strict_types=1);`.
- Follow `PSR12Ext` (`roslov/psr12ext`) coding style; PHPStan runs at `level: max`; Rector must report no changes.
- Prefer `final` and `readonly` classes; use typed class constants (`private const string ...`).
- Import global functions explicitly (`use function sprintf;`) as existing files do.
- Throw bundle exceptions from `src/Exception` (or SPL `LogicException`/`InvalidArgumentException` for misuse).
- Keep the bundle framework-light: require only what `composer.json` already requires.
- Never log message bodies without `LogObfuscator` (`roslov/log-obfuscator`).

## Testing rules

Every behavioral change requires relevant PHPUnit tests under `tests/` (see `docs/testing.md`):

- New classes must be covered by unit tests; updated classes must have their tests updated.
- Tests must not access networks, databases, or a live RabbitMQ broker; use stubs/mocks and `ContainerBuilder`.
- Cover both success and failure paths (exceptions, disabled features, invalid configuration).
- Link the class to its test with an `@see <TestClass>` tag in the class doc block, as existing classes do.

## Required commands

Run inside the PHP 8.3 Docker container (see "Execution environment"). Before completion, run:

```bash
composer test:static
composer test:unit
composer test:integration
```

`composer test:integration` is currently a placeholder (`Integration tests are not implemented.`).
If a command cannot run, report the exact blocker. Do not represent an unexecuted check as passing.

## Code documentation rules

- All classes, interfaces, traits, methods, properties, and constants must have PHPDoc doc blocks.
- Doc blocks describe purpose, `@param`, `@return`, `@throws`, and behavioral constraints.
- Use `@inheritDoc` for overridden/implemented methods.

## Documentation requirements

Update `README.md` and `docs/` when changing:

- the `roslov_queue` configuration tree or defaults;
- public classes, interfaces, service IDs, or aliases;
- the message envelope, default payloads, routing keys, or the `event` table schema;
- RPC, event processor, or exception-event behavior;
- supported PHP/Symfony/Doctrine/RabbitMQ bundle versions (`composer.json`, `.github/workflows/`);
- build, test, Docker, or CI workflows.

All Markdown files (`*.md`) must adhere to the 120-character line length limit.

## Skill maintenance rules

Whenever changing functionality or workflows, update the corresponding workspace skill under `.agents/skills/`:

- Docker execution, verification commands, static analysis, CI matrix ->
  `.agents/skills/docker-verification/SKILL.md`
- PHPUnit tests, fixtures, testing conventions ->
  `.agents/skills/bundle-testing/SKILL.md`
- Configuration tree, extension, prepend logic, `services.yaml`, compiler passes ->
  `.agents/skills/bundle-configuration-and-di/SKILL.md`
- Message envelope, DTOs, serializer, producers, event processor, RPC, exception events ->
  `.agents/skills/queue-messaging-and-rpc/SKILL.md`

## Definition of done

Work is complete only when:

- the requested behavior is implemented;
- backward compatibility is preserved, or a requested break is documented;
- all classes, methods, properties, and constants have PHPDoc doc blocks;
- strict types are declared in all PHP files;
- no software was installed on the host machine or WSL, and all commands ran inside a PHP 8.3 Docker container;
- acceptance criteria in `docs/acceptance-criteria.md` are satisfied;
- tests are added for success and failure paths;
- `composer test:static`, `composer test:unit`, and `composer test:integration` pass;
- `README.md` and `docs/` describe the resulting behavior;
- relevant workspace skills under `.agents/skills/` are updated;
- all Markdown files adhere to the 120-character line length limit;
- no git commits or pushes are made without an explicit user request;
- the final report lists changed files, checks run, and remaining risks.
