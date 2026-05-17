# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

**Backend**
- **PHP 8.5** / **Symfony 8.0** skeleton
- **FrankenPHP** (built on Caddy) as web server — replaces nginx/apache
- **Doctrine ORM 3.6** with **MySQL 8** (default in `compose.yaml`)
- **Symfony Messenger** — dual bus CQRS (`command.bus` / `query.bus`)
- **dompdf/dompdf** — PDF generation for expense export
- **Docker Compose** for local development; all dev commands run inside containers via `make`

**Frontend** (`frontend/`)
- **Vue 3** (Composition API + `<script setup>`)
- **Vite** — dev server proxies `/api` → Symfony :443
- **Pinia** — state management (`authStore`, `personStore`, `expenseStore`)
- **Tailwind CSS** — utility-first styling
- **TypeScript** + **Axios** — typed API client in `frontend/src/api/`

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

DDD + CQRS. **Never put business logic in controllers or infrastructure handlers.**

```
src/
  Auth/               # Bounded context — JWT auth, User entity, registration/login
  Person/             # Bounded context — Person aggregate (linked to a User)
  Expense/            # Bounded context — all expense types + PDF/CSV export
  Billing/            # Bounded context — Stripe subscription, SubscriptionMiddleware
  Admin/              # Bounded context — admin dashboard, user management
  SharedKernel/       # CommandBusInterface, QueryBusInterface, base value objects
  Kernel.php
config/
  packages/           # Per-bundle YAML config (doctrine, messenger, security…)
  services.yaml       # Autowire + autoconfigure enabled globally
migrations/           # DoctrineMigrations (namespace DoctrineMigrations)
frontend/
  src/
    api/              # Axios clients (http.ts, expenseApi.ts, personApi.ts, adminApi.ts)
    stores/           # Pinia stores (authStore, personStore, expenseStore)
    views/            # Page components (CalendarView, SummaryView, PersonsView…)
    components/       # Reusable UI components
    types/index.ts    # Shared TypeScript interfaces
```

**Layer rules per bounded context:**
```
Domain/          # Entities, Value Objects, Enums, Domain Services, Repository interfaces
Application/
  Command/       # CommandHandler (write side) — dispatched via command.bus
  Query/         # QueryHandler (read side) — dispatched via query.bus
Infrastructure/
  Http/          # Symfony controllers (thin — delegate to bus immediately)
  Persistence/   # Doctrine repository implementations
```

**Expense STI hierarchy** (Doctrine Single Table Inheritance):
- `Expense` (base) → `TravelExpense`, `RemoteWorkExpense`, `TollExpense`, `MealExpense`
- `KilometricAllowanceCalculator` — barème kilométrique 2024 (3–7 CV, voiture/moto, électrique +20%)

**Subscription guard:**
- `SubscriptionMiddleware` blocks all `/api/*` routes (except `/api/auth/`, `/api/billing/`, `/api/admin/`) when `user.subscriptionStatus !== 'active'` → HTTP 402
- Frontend `http.ts` interceptor redirects to `/pricing` on 402

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

## Frontend Dev Commands

The frontend is a separate Vite app in `frontend/`. Run outside Docker:

```bash
cd frontend
npm install       # install dependencies
npm run dev       # start Vite dev server (proxies /api → https://localhost)
npm run build     # production build into frontend/dist/
npm run typecheck # run tsc --noEmit
```

## Key API Routes

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/api/auth/register` | Register a new user |
| POST | `/api/auth/login` | Login → JWT token |
| GET | `/api/auth/me` | Current user profile |
| GET/POST | `/api/persons` | List / create persons |
| PUT/DELETE | `/api/persons/{id}` | Update / delete a person |
| GET/POST | `/api/expenses` | List by period / create expense |
| PATCH/DELETE | `/api/expenses/{id}` | Update / delete expense |
| GET | `/api/expenses/summary` | JSON summary by person + year |
| GET | `/api/expenses/summary/pdf` | PDF export |
| GET | `/api/expenses/summary/csv` | CSV export (Excel-compatible) |
| GET | `/api/admin/users` | Admin — paginated user list |
| GET | `/api/admin/users/export` | Admin — CSV export all users |

## Dev Fixtures

Commande pour (re)charger un jeu de données complet en local :

```bash
make fixtures   # drop DB → create → migrate → fixtures:load
```

### Utilisateurs

| Email | Mot de passe | Rôle | Abonnement | Année | CV fiscal |
|-------|-------------|------|-----------|-------|-----------|
| `admin@fraisreel.fr` | `Admin1234!` | ROLE_ADMIN | active | 2025 | 5 |
| `alice@example.com` | `Test1234!` | ROLE_USER | active | 2025 | 5 |
| `bob@example.com` | `Test1234!` | ROLE_USER | active | 2025 | 3 |
| `carol@example.com` | `Test1234!` | ROLE_USER | _(aucun)_ | — | — |

> Carol n'a pas d'abonnement actif → toutes les routes `/api/*` retournent 402.

### Personnes & routes favorites

| Personne | Compte | Trajet domicile-travail | Véhicule | CV |
|----------|--------|------------------------|----------|----|
| Alice Dupont _(self)_ | alice | Paris 13e → La Défense (18 km) | Voiture | 5 |
| Marc Dupont | alice | Lyon 3e → Villeurbanne (8 km) | Voiture | 3 |
| Robert Martin _(self)_ | bob | Bordeaux Centre → Mérignac (15 km) | Voiture / Moto | 3/5 |

### Dépenses chargées

| Personne | Période | Types |
|----------|---------|-------|
| Alice Dupont | Jan–Déc 2025 (sans août) + Oct–Déc 2024 | Trajet, télétravail, péage, repas, parking, électrique |
| Marc Dupont | Jan–Mars 2025 | Trajet, télétravail, repas |
| Robert Martin | Jan–Mars 2025 | Trajet (voiture jan-fév, moto mars), télétravail, repas, péage |

### Référentiel (ReferentialFixtures)

| Table | Années |
|-------|--------|
| `fiscal_config` | 2023 (2,50 €/j · repas 4,85 €), 2024 (2,70 € · 5,35 €), 2025 (2,70 € · 5,45 €) |
| `bareme_kilometrique` | 2023 · 2024 · 2025 · 2026 — arrêté du 27 mars 2023 |

<!-- rtk-instructions v2 -->
# RTK (Rust Token Killer) - Token-Optimized Commands

## Golden Rule

**Always prefix commands with `rtk`**. If RTK has a dedicated filter, it uses it. If not, it passes through unchanged. This means RTK is always safe to use.

**Important**: Even in command chains with `&&`, use `rtk`:
```bash
# ❌ Wrong
git add . && git commit -m "msg" && git push

# ✅ Correct
rtk git add . && rtk git commit -m "msg" && rtk git push
```

## RTK Commands by Workflow

### Build & Compile (80-90% savings)
```bash
rtk cargo build         # Cargo build output
rtk cargo check         # Cargo check output
rtk cargo clippy        # Clippy warnings grouped by file (80%)
rtk tsc                 # TypeScript errors grouped by file/code (83%)
rtk lint                # ESLint/Biome violations grouped (84%)
rtk prettier --check    # Files needing format only (70%)
rtk next build          # Next.js build with route metrics (87%)
```

### Test (60-99% savings)
```bash
rtk cargo test          # Cargo test failures only (90%)
rtk go test             # Go test failures only (90%)
rtk jest                # Jest failures only (99.5%)
rtk vitest              # Vitest failures only (99.5%)
rtk playwright test     # Playwright failures only (94%)
rtk pytest              # Python test failures only (90%)
rtk rake test           # Ruby test failures only (90%)
rtk rspec               # RSpec test failures only (60%)
rtk test <cmd>          # Generic test wrapper - failures only
```

### Git (59-80% savings)
```bash
rtk git status          # Compact status
rtk git log             # Compact log (works with all git flags)
rtk git diff            # Compact diff (80%)
rtk git show            # Compact show (80%)
rtk git add             # Ultra-compact confirmations (59%)
rtk git commit          # Ultra-compact confirmations (59%)
rtk git push            # Ultra-compact confirmations
rtk git pull            # Ultra-compact confirmations
rtk git branch          # Compact branch list
rtk git fetch           # Compact fetch
rtk git stash           # Compact stash
rtk git worktree        # Compact worktree
```

Note: Git passthrough works for ALL subcommands, even those not explicitly listed.

### GitHub (26-87% savings)
```bash
rtk gh pr view <num>    # Compact PR view (87%)
rtk gh pr checks        # Compact PR checks (79%)
rtk gh run list         # Compact workflow runs (82%)
rtk gh issue list       # Compact issue list (80%)
rtk gh api              # Compact API responses (26%)
```

### JavaScript/TypeScript Tooling (70-90% savings)
```bash
rtk pnpm list           # Compact dependency tree (70%)
rtk pnpm outdated       # Compact outdated packages (80%)
rtk pnpm install        # Compact install output (90%)
rtk npm run <script>    # Compact npm script output
rtk npx <cmd>           # Compact npx command output
rtk prisma              # Prisma without ASCII art (88%)
```

### Files & Search (60-75% savings)
```bash
rtk ls <path>           # Tree format, compact (65%)
rtk read <file>         # Code reading with filtering (60%)
rtk grep <pattern>      # Search grouped by file (75%)
rtk find <pattern>      # Find grouped by directory (70%)
```

### Analysis & Debug (70-90% savings)
```bash
rtk err <cmd>           # Filter errors only from any command
rtk log <file>          # Deduplicated logs with counts
rtk json <file>         # JSON structure without values
rtk deps                # Dependency overview
rtk env                 # Environment variables compact
rtk summary <cmd>       # Smart summary of command output
rtk diff                # Ultra-compact diffs
```

### Infrastructure (85% savings)
```bash
rtk docker ps           # Compact container list
rtk docker images       # Compact image list
rtk docker logs <c>     # Deduplicated logs
rtk kubectl get         # Compact resource list
rtk kubectl logs        # Deduplicated pod logs
```

### Network (65-70% savings)
```bash
rtk curl <url>          # Compact HTTP responses (70%)
rtk wget <url>          # Compact download output (65%)
```

### Meta Commands
```bash
rtk gain                # View token savings statistics
rtk gain --history      # View command history with savings
rtk discover            # Analyze Claude Code sessions for missed RTK usage
rtk proxy <cmd>         # Run command without filtering (for debugging)
rtk init                # Add RTK instructions to CLAUDE.md
rtk init --global       # Add RTK to ~/.claude/CLAUDE.md
```

## Token Savings Overview

| Category | Commands | Typical Savings |
|----------|----------|-----------------|
| Tests | vitest, playwright, cargo test | 90-99% |
| Build | next, tsc, lint, prettier | 70-87% |
| Git | status, log, diff, add, commit | 59-80% |
| GitHub | gh pr, gh run, gh issue | 26-87% |
| Package Managers | pnpm, npm, npx | 70-90% |
| Files | ls, read, grep, find | 60-75% |
| Infrastructure | docker, kubectl | 85% |
| Network | curl, wget | 65-70% |

Overall average: **60-90% token reduction** on common development operations.
<!-- /rtk-instructions -->
