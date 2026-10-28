---
name: docker-verification
description: >-
  Enforce Docker-only execution in a PHP 8.3 container and run verification commands (Composer validation,
  syntax, coding style, PHPStan, Rector, PHPUnit) for Roslov Queue Bundle. Use when validating changes,
  running tests, installing dependencies, or checking code before completion.
---

# Docker Verification Workflow

All project-related console commands for Roslov Queue Bundle must run inside a **PHP 8.3** Docker container.
PHP 8.3 is the lowest version supported by `composer.json` (`"php": "^8.3"`), so it catches use of newer syntax.

## Execution Environment Model

1. **Host machine / WSL (project root)**:
   - Only `docker`, `docker compose`, and `git` (read-only, unless the user asks for commits) are executed here.
   - **Never** run `php`, `composer`, `vendor/bin/*` on the host, even if a host PHP exists.
   - **Never** install packages, runtimes, or extensions on the host or in WSL.
2. **Docker container (`/app`)**:
   - Environment managed via Docker Compose (`compose.yaml`): PHP 8.3, Composer 2, `json`, `mbstring`,
     `sockets`, `pcov` installed via PIE.
   - Run as the host user so files in `vendor/` and caches stay owned by the user.
   - Set `COMPOSER_HOME` to a writable path (`/tmp/composer`).

> [!CAUTION]
> Agents must not install anything on the host or WSL. All console commands must run inside the container via
> Docker Compose. To build or rebuild the development environment, run `docker compose build`.

> [!IMPORTANT]
> No task may be declared complete while a required verification command is failing, skipped without explanation,
> or unexecuted.

---

## Command Template

All commands are executed using Docker Compose from the project root:

```bash
docker compose run --rm app <command>
```

Build or rebuild the development image:

```bash
docker compose build
```

Docker access may require running the command outside the agent sandbox (the Docker socket is not available inside it).

---

## Verification Commands

| Purpose                         | `<command>`                                                  |
|---------------------------------|--------------------------------------------------------------|
| Full static suite               | `composer test:static`                                       |
| Unit tests                      | `composer test:unit`                                         |
| Integration tests (placeholder) | `composer test:integration`                                  |
| Everything                      | `composer test`                                              |
| Single test class               | `vendor/bin/phpunit tests/Helper/ExceptionShortenerTest.php` |
| Single test method              | `vendor/bin/phpunit --filter testProcessWithTaggedPayload`   |
| Fix coding style                | `composer phpcbf .`                                          |
| Apply Rector fixes              | `vendor/bin/rector`                                          |
| Install dependencies            | `composer install`                                           |

`composer test:static` runs, in order:

1. `composer validate` — validates `composer.json`.
2. `composer normalize --dry-run` — enforces normalized `composer.json` (`ergebnis/composer-normalize`).
3. `composer syntax .` — `parallel-lint` (excludes `bin`, `vendor`, `var`).
4. `composer phpcs .` — PHP_CodeSniffer with the `PSR12Ext` standard (`roslov/psr12ext`).
5. `composer phpstan` — PHPStan `level: max` on `src/` and `tests/` with the Doctrine extension.
6. `rector --dry-run` — Rector (`rector.php`) must report no changes.

### Dependency notes

- `composer.lock` is git-ignored; `composer install` may resolve versions locally. Do not commit the lock file.
- If `vendor/` was installed with a newer PHP and fails on 8.3, run `composer update` inside the 8.3 container and
  report it. Do not change constraints in `composer.json` without a request.
- CI (`.github/workflows/test.yml`, `static.yml`) runs `composer update` on PHP 8.3, 8.4, and 8.5. When a change
  might behave differently on newer PHP, mention it in the report; the only local image required is PHP 8.3.

---

## Pre-Completion Checklist

1. `declare(strict_types=1);` and complete PHPDoc doc blocks on all new or changed PHP code.
2. Tests added/updated for success and failure paths (see `bundle-testing` skill).
3. `composer test:static`, `composer test:unit`, `composer test:integration` pass in the PHP 8.3 container.
4. `README.md`, `docs/`, and `.agents/skills/` updated for behavior or workflow changes.
5. Markdown lines are at most 120 characters:
   `awk 'length > 120 {print FILENAME": "FNR}' AGENTS.md README.md docs/*.md .agents/skills/*/SKILL.md`
   (`awk` is a read-only host utility, not a project command).
6. No commits or pushes without an explicit user request.

---

## Skill Maintenance & Self-Enforcement

Whenever the Docker image, Composer scripts, static analysis tools, or CI matrix change, update this file
(`.agents/skills/docker-verification/SKILL.md`), `AGENTS.md`, and `docs/testing.md`.
