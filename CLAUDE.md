# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- **PHP 8.5** / **Symfony 8.0** skeleton
- **FrankenPHP** (built on Caddy) as web server — replaces nginx/apache
- **Doctrine ORM 3.6** with **PostgreSQL** (default in `.env`) or **MySQL 8** (default in `compose.yaml`)
- **Docker Compose** for local development; all dev commands run inside containers via `make`

## Common Commands

All commands run via `make`. The app runs inside Docker — do not invoke `php`/`composer`/`bin/console` directly on the host.

```bash
make start          # build images + start containers
make up             # start containers (no rebuild)
make down           # stop containers
make logs           # tail container logs
make sh             # shell into the FrankenPHP container
make bash           # bash into the FrankenPHP container

make vendor         # install dependencies (composer install from lock)
make composer c='require symfony/validator'  # run any composer command
make sf c='make:entity Foo'                  # run any bin/console command
make cc             # clear Symfony cache

make test           # run PHPUnit
make test c='tests/Unit/FooTest.php'         # run a single test file
```

## Architecture

```
src/
  Kernel.php          # MicroKernelTrait; bundles declared in config/bundles.php
  Controller/         # HTTP controllers (attribute-based routing)
  Entity/             # Doctrine ORM entities (attribute-based mapping)
  Repository/         # Doctrine repositories
config/
  packages/           # Per-bundle YAML config (doctrine, framework, cache…)
  routes.yaml         # Loads routes from src/Controller via attribute routing
  services.yaml       # Autowire + autoconfigure enabled globally
migrations/           # DoctrineMigrations (namespace DoctrineMigrations)
public/index.php      # Entry point — Symfony Runtime bootstrap
```

**Naming strategy:** Doctrine converts entity class names to snake_case table names automatically.

## Environment

- `.env` — base defaults (committed; no secrets)
- `.env.dev` — local dev overrides (not committed)
- Key variables: `APP_ENV`, `APP_SECRET`, `DATABASE_URL`, `DEFAULT_URI`, `APP_SHARE_DIR`
- Compose injects `MYSQL_*` / `MERCURE_*` variables; see `compose.yaml` for the full list.

## Docker Compose Files

| File | Purpose |
|------|---------|
| `compose.yaml` | Base services (`php`, `database`) |
| `compose.override.yaml` | Dev overrides (port exposure, hot-reload, Xdebug) |
| `compose.prod.yaml` | Production target |

The `Dockerfile` uses multi-stage builds: `frankenphp_dev` for development (Xdebug included), `frankenphp_prod` for production.

## Doctrine Migrations Workflow

```bash
make sf c='doctrine:migrations:diff'    # generate migration from entity changes
make sf c='doctrine:migrations:migrate' # apply pending migrations
```

Migrations live in `migrations/` with namespace `DoctrineMigrations`.
