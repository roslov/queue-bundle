# Acceptance Criteria

## Environment and repository hygiene

- [ ] All project commands run inside a PHP 8.3 Docker container; nothing is installed on the host or WSL.
- [ ] `composer.lock`, `vendor/`, `.idea/`, `.phpunit.cache/`, and `.phpunit.result.cache` are not committed.
- [ ] Development-only files are `export-ignore`d in `.gitattributes`.
- [ ] No real credentials, hostnames, or production endpoints appear in code, tests, or documentation.

## Compatibility

- [ ] The bundle installs and works on PHP 8.3, 8.4, and 8.5 with Symfony 6 and 7 (`composer.json`, CI matrix).
- [ ] Public API, configuration keys and defaults, service IDs/aliases, tags, and prepended RabbitMQ names remain
      backward compatible unless a break is explicitly requested and documented.
- [ ] The JSON envelope (`type`, `source`, `correlationId`, `data`) and default message types are unchanged.

## Configuration and DI

- [ ] Every `roslov_queue` option is defined in `Configuration`, applied in `RoslovQueueExtension`, and documented in
      `README.md` with the same default value.
- [ ] Container compilation succeeds with default configuration and with attribute-mapped payloads/handlers.
- [ ] Explicit YAML payload mapping takes precedence over attributes; duplicate attribute types fail compilation.

## Messaging behavior

- [ ] A disabled event processor performs no I/O.
- [ ] Instant delivery publishes immediately without touching the database.
- [ ] Transactional outbox stores events (buffered while a transaction is active) and sends them in order on
      `kernel.terminate` and AMQP consume events, deleting each sent event.
- [ ] RPC client/server round-trip returns the handler DTO or an `Error` DTO; timeouts and unknown commands raise
      the documented exceptions.
- [ ] Exception events respect `enabled`, the validator, and the HTTP 500-only rule, and are size-bounded.

## Quality gates

- [ ] `composer test:static` passes (validate, normalize, syntax, PHPCS `PSR12Ext`, PHPStan `level: max`, Rector).
- [ ] `composer test:unit` passes with no risky tests, warnings, or notices.
- [ ] `composer test:integration` runs without errors.
- [ ] New or changed classes have PHPUnit tests for success and failure paths and an `@see` link to the test.
- [ ] All PHP files declare strict types and have complete PHPDoc doc blocks.

## Documentation

- [ ] `README.md` and `docs/` describe the resulting behavior.
- [ ] Workspace skills under `.agents/skills/` reflect the current architecture and workflows.
- [ ] All Markdown files adhere to the 120-character line length limit.
