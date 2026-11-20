# Inventory Assets

Asset inventory for one organisation (~100 users, ~1,000 assets) with a read-only chatbot that answers within each user's access rights. Laravel 13 + Inertia 3 + React 19, PostgreSQL. Project rules for contributors and AI agents: [CLAUDE.md](CLAUDE.md).

## Versions

Local and server must run the same PHP and PostgreSQL versions, so deploy day has no surprises.

| Component         | Local (Windows, PhpWebStudy) | Server target (Ubuntu 24.04 LTS)           | CI (GitHub Actions) |
| ----------------- | ---------------------------- | ------------------------------------------ | ------------------- |
| PHP               | 8.4.25                       | 8.4 via `ppa:ondrej/php`                   | 8.4                 |
| PostgreSQL        | 17.10                        | 17 via the PGDG apt repo                   | 17                  |
| Node (build only) | 24.12                        | not installed; assets are built locally/CI | 24                  |
| Composer          | 2.10                         | 2.x                                        | 2.x                 |

Ubuntu 24.04 ships PHP 8.3 and PostgreSQL 16 by default, so the server must add the two repositories above. The VPS provider is still open (see CLAUDE.md).

## Local setup

Requires PHP 8.4 (`pdo_pgsql`), Composer, Node 24 and a running PostgreSQL 17 on `127.0.0.1:5432`.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Create the two databases (dev + tests; tests never use SQLite):

```bash
psql -h 127.0.0.1 -U postgres -c "CREATE DATABASE inventory_assets;" -c "CREATE DATABASE inventory_assets_test;"
```

Then seed the demo data and run the app:

```bash
php artisan migrate:fresh --seed
composer dev
```

Put a Gemini key from Google AI Studio in `.env` as `GEMINI_API_KEY` for the chatbot.

## Demo accounts

All use the password `password` (demo only, not public).

| Role            | Email                |
| --------------- | -------------------- |
| Admin           | `admin@demo.test`    |
| Warehouse staff | `staff@demo.test`    |
| Employee        | `employee@demo.test` |

The seed creates ~100 users, 8 categories, 9 locations, 1,000 assets and a year of loans, movements and maintenance, dated relative to today so overdue loans always exist.

## Checks

```bash
composer test
```

Runs Pint, PHPStan (level 7) and PHPUnit against `inventory_assets_test`. CI runs the same on every push.

## Known local issue: Avast

Avast Web Shield's HTTPS scanning breaks PHP/Composer/npm TLS (`cURL error 60`), and its file shield has quarantined `phpstan.neon`. Exclude this folder and turn off HTTPS scanning (or exclude `generativelanguage.googleapis.com`, `repo.packagist.org`, `registry.npmjs.org`).
