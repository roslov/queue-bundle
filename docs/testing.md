# Testing

## Overview

The bundle is tested with **PHPUnit 12** (`phpunit/phpunit ^12.5`, configured by `phpunit.xml.dist`). All tests live in
`tests/` under the namespace `Roslov\QueueBundle\Tests\` and mirror the `src/` structure.

| Suite / command             | Tooling                                             | Status               |
|-----------------------------|-----------------------------------------------------|----------------------|
| `composer test:unit`        | PHPUnit (`Queue bundle tests` suite, `./tests`)     | Implemented          |
| `composer test:static`      | validate, normalize, syntax, PHPCS, PHPStan, Rector | Implemented          |
| `composer test:integration` | —                                                   | Placeholder (notice) |
| `composer test`             | All of the above                                    | —                    |

PHPUnit runs with `failOnRisky`, `failOnWarning`, and `error_reporting=-1`: tests without assertions, warnings, and
notices fail the build.

## Execution environment

Run every command inside a PHP 8.3 Docker container from the project root (see
`.agents/skills/docker-verification/SKILL.md`):

```bash
docker compose run --rm app composer test:unit
```

Nothing may be installed on the host machine or WSL.

## Test layout

```text
tests/
├── Attribute/             # AsQueuePayload
├── DependencyInjection/   # RoslovQueueExtension, Compiler/PayloadMappingPass
├── Fixtures/              # Dummy DTOs and RPC handlers shared by tests
├── Helper/                # ExceptionShortener
└── RoslovQueueBundleTest.php  # Bundle build() and full container compilation
```

## Rules

- Every new class needs a test class at the mirrored path (`src/Foo/Bar.php` -> `tests/Foo/BarTest.php`).
- Add `@see <TestClass>` to the production class doc block (import the test class with `use`).
- Test classes are `final`, extend `PHPUnit\Framework\TestCase`, and every test method has a doc block.
- Cover success and failure paths: disabled features, missing entity manager, invalid handlers, unknown message types,
  duplicate payload types, timeouts, and storage failures.
- Do not connect to RabbitMQ, a database, or the network. Use:
  - `createStub()` when no interaction is verified (Rector's `phpunitMockToStub` set enforces this);
  - `createMock()` with `expects()` only when verifying calls;
  - `ContainerBuilder` + `Definition` for DI tests (see `RoslovQueueBundleTest::testFullContainerCompilation()`).
- Put reusable dummy classes in `tests/Fixtures` (`final`, documented, `Fixture: ...` summary).
- Data providers use the `#[DataProvider]` attribute and must be declared as `public static`.
- Use `assertSame()` over `assertEquals()`; PHPStan (`level: max`) analyses `tests/` as well, so add precise
  `@var` annotations when reading untyped values such as `Definition::getArgument()`.

## Coverage priorities

Currently tested: `AsQueuePayload`, `RoslovQueueBundle`, `RoslovQueueExtension` (payload mapping),
`PayloadMappingPass`, `ExceptionShortener`.

Not yet covered (add tests when touching these classes): `EventProcessor`, `MessagePayloadSerializer`,
`PayloadFactory`, `MessageFactory`, `Rpc\Client`, `Rpc\Server`, `ExceptionSender`, `ExceptionSubscriber`,
`EventSenderSubscriber`, `ProducerLocator`, `BaseProducer`, `SimpleSslContextProvider`, and `prepend()` of the
extension.

## Code coverage

`phpunit.xml.dist` includes `./src` for coverage. The development `Dockerfile` and CI include `pcov`,
so coverage can be collected directly:

```bash
docker compose run --rm app vendor/bin/phpunit --coverage-text
```
