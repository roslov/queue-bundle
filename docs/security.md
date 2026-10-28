# Security

## Security goals

- Never leak RabbitMQ credentials, database credentials, or sensitive payload data through logs or events.
- Keep deserialization restricted to explicitly mapped DTO classes.
- Bound the size of data that leaves a service in exception events.
- Avoid shipping development or agent tooling to library consumers.

## Trust boundaries

| Boundary                       | Trust level              | Controls in the bundle                               |
|--------------------------------|--------------------------|------------------------------------------------------|
| Incoming AMQP message bodies   | Untrusted input          | JSON decode with `JSON_THROW_ON_ERROR`; allow-list   |
| Host application configuration | Trusted                  | Validated by `Configuration` tree                    |
| RabbitMQ broker connection     | Infrastructure           | Host app configured; optional SSL context provider   |
| Outbox `event` table           | Trusted, internal        | Doctrine ORM; parameterized queries only             |
| Exception events               | Internal, may carry data | Shortened payload; optional exception validator      |

## Deserialization

- `MessagePayloadSerializer::deserialize()` maps `type` to a class only through `PayloadFactory`'s configured mapping.
  Unknown types raise `InvalidMessageTypeException`, wrapped in `DeserializationException`.
- Never deserialize into a class name taken from message content. Do not add `unserialize()` or polymorphic
  type discriminators driven by untrusted input.
- Exception messages include the offending JSON truncated to 1000 characters; consumers must treat them as
  potentially sensitive.

## Logging and data exposure

- `EventProcessor::send()` logs payloads only through `LogObfuscator::obfuscate()`.
- The outbox debug log (`Saving the queue message ...`) currently logs the raw body at `debug` level; do not
  extend raw-body logging to higher levels.
- Exception events contain the service name, hostname, exception class, file, line, shortened message (400),
  shortened trace (2700), and shortened `REQUEST_URI` (400). Query strings in URIs and exception messages may contain
  secrets; consumers should use `exception_subscriber.exception_validator` to filter exceptions.
- Do not add credentials, tokens, or personal data to default payloads.

## Configuration and secrets

- The bundle never reads secrets directly; connection URLs are owned by the host application
  (`old_sound_rabbit_mq.connections.*.url`, typically `%env(RABBITMQ_URL)%`).
- Do not commit real credentials, hostnames, or production endpoints in tests, fixtures, docs, or examples.
  Use placeholders such as `amqp://guest:guest@rabbitmq:5672`.
- `ssl_enabled` is a `scalarNode` so it can be driven by environment variables; keep it that way.

## Resource limits

- RPC calls are bounded by a timeout (default 15 seconds) and raise `TimeoutRpcClientException`.
- The RPC server uses prefetch count 1.
- Exception payloads are bounded by `ExceptionShortener` so that events fit message size limits.

## Supply chain and distribution

- Do not add dependencies without an explicit request; keep `composer.json` constraints minimal.
- `composer.lock` is git-ignored for this library.
- `.gitattributes` excludes development files (`/tests`, `/.github`, `/AGENTS.md`, `/.agents`, configs) from the
  Packagist dist archive.
- All tooling runs inside Docker containers; nothing is installed on the host or WSL.
