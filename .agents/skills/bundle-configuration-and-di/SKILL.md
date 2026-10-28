---
name: bundle-configuration-and-di
description: >-
  Add, change, or remove roslov_queue configuration options and keep Configuration, RoslovQueueExtension
  (load/prepend), services.yaml, PayloadMappingPass, README.md, and tests in sync for Roslov Queue Bundle.
  Use when touching src/DependencyInjection, src/Resources/config/services.yaml, RoslovQueueBundle, or service wiring.
---

# Bundle Configuration and DI Workflow

## Moving Parts

- `src/DependencyInjection/Configuration.php`:
  `roslov_queue` tree, defaults, `info()`/`example()`, `DEFAULT_PAYLOADS`.
- `src/DependencyInjection/RoslovQueueExtension.php`:
  `load()`: loads `services.yaml`, replaces arguments by index; `prepend()`: RabbitMQ config.
- `src/Resources/config/services.yaml`:
  `roslov_queue.*` definitions with placeholder arguments and public aliases.
- `src/DependencyInjection/Compiler/PayloadMappingPass.php`:
  Merges `roslov_queue.payload` tags into payload mapping and RPC handlers.
- `src/RoslovQueueBundle.php`:
  `#[AsQueuePayload]` autoconfiguration, registers the pass.
- `README.md` ("Default bundle configuration"):
  Public, commented reference config.

## Rules

- Configuration keys, defaults, service IDs, aliases, the `roslov_queue.payload` tag, and prepended names
  (`roslov_queue` RPC client/server, `event_exception_thrown` producer) are **public API**. Do not rename or change
  defaults without an explicit request; add new options with backward-compatible defaults.
- Use `scalarNode` (not `booleanNode`) for options that must accept `%env()%` values (see `ssl_enabled`).
- Service arguments are replaced **by position**. When a constructor changes, update the constructor, the
  placeholder list in `services.yaml`, every `replaceArgument(<index>, ...)`, and the tests in one change.
- `prepend()` must only call `prependExtensionConfig('old_sound_rabbit_mq', ...)` when that extension is registered.
- Optional services (entity manager, validator, setup callable) are `null` when not configured; consumers must handle
  `null` and raise `LogicException` with a clear message when used without configuration.
- Keep `PayloadMappingPass` precedence: explicit YAML types > attribute types > defaults; conflicting attribute types
  throw `LogicException`; clean temporary parameters and tags after processing.

---

## Step-by-Step: Adding a Configuration Option

1. **Tree**: add the node in `Configuration::getConfigTreeBuilder()` with `info()`, `example()`, and a default
   that preserves current behavior.
2. **Wiring**: in `RoslovQueueExtension::load()`, pass the value to the target service with
   `replaceArgument()` (or `setParameter()` if it must be read by a compiler pass).
3. **Service**: add the constructor parameter (typed, `readonly`, documented) and a placeholder argument in
   `services.yaml` at the same position.
4. **Prepend** (only if it affects RabbitMQ bundle config): extend `prepend()`.
5. **Tests**: extend `tests/DependencyInjection/RoslovQueueExtensionTest.php` for default and custom values; add a
   full-compilation assertion in `tests/RoslovQueueBundleTest.php` if wiring across services changes.
6. **Docs**: add the option to the YAML block in `README.md` with a comment and the same default, and update
   `docs/requirements.md` / `docs/architecture.md` if behavior changes.
7. **Verify** in the PHP 8.3 container: `composer test:static && composer test:unit`.

## Step-by-Step: Adding a Public Service

1. Define `roslov_queue.<area>.<name>` in `services.yaml`.
2. If applications should autowire it, add an alias from the class or interface to the service ID.
3. Document it in `docs/architecture.md` ("Public services and aliases") and, if user-facing, in `README.md`.
4. Add a container test asserting the definition/alias exists and resolves.

## Example: Extension Test Skeleton

```php
/**
 * Tests that the custom service name is passed to the message factory.
 */
public function testLoadPassesServiceName(): void
{
    $container = new ContainerBuilder();
    (new RoslovQueueExtension())->load([['service_name' => 'billing']], $container);

    $this->assertSame('billing', $container->getDefinition('roslov_queue.message_factory')->getArgument(1));
    $this->assertSame('billing', $container->getDefinition('roslov_queue.exception_sender')->getArgument(2));
}
```

---

## Skill Maintenance & Self-Enforcement

When the configuration tree, service definitions, extension logic, or compiler passes change, update this file
(`.agents/skills/bundle-configuration-and-di/SKILL.md`), `README.md`, and `docs/architecture.md`.
