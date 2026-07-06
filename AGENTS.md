# AGENTS.md

## Cursor Cloud specific instructions

Middo is a single **Laravel 13 + Livewire 4** monolith (a multi-role food-delivery platform). It is not a monorepo. Standard commands live in `composer.json` (`scripts`), `package.json`, and are documented at the Laravel level; prefer those over duplicating here.

The update script already installs system PHP 8.3 (+ extensions), Composer deps, and npm deps, and prepares the `.env` + SQLite database. The notes below cover only non-obvious runtime gotchas.

### Environment / DB
- Default DB is **SQLite** at `database/database.sqlite` (per `.env.example`). No external DB daemon is required. The `.devcontainer` uses MariaDB, but that is Codespaces-only and not needed here.
- After changing migrations/seeders, reset with `php artisan migrate:fresh --seed` (SQLite is a local file).
- Seeded login accounts (all password `12345678`) are created by `database/seeders/userSeeder.php`, e.g. admin mobile `01310123451`, plus `corporate`/`kitchen`/`delivery`/`operation` users.

### Login gotcha
- The login form authenticates by **mobile number, not email** (field name `mobile`). Use the seeded mobile numbers (admin = `01310123451`), not the email addresses, when logging in.

### Frontend assets — important
- `vite.config.js` hardcodes HMR to `clientPort: 443` / `protocol: 'wss'` (built for GitHub Codespaces). Running `npm run dev` in this VM writes a `public/hot` file pointing at `https://localhost:443`, which breaks all asset loading in the browser.
- For a working browser UI here, use **`npm run build`** (static assets via `public/build/manifest.json`) instead of the Vite dev server. If a stale `public/hot` file exists, delete it so Laravel serves the built assets.

### HTTPS is forced — important
- `app/Providers/AppServiceProvider.php` calls `URL::forceScheme('https')` unconditionally, so every generated URL (assets, redirects) is `https://localhost:8000`. Plain `php artisan serve` only speaks HTTP, so the browser fails to load assets over `http://`.
- To view the app in a browser, terminate TLS on port 8000. Working setup used during env bootstrap:
  1. `php artisan serve --host=127.0.0.1 --port=8001` (HTTP backend)
  2. Self-signed cert: `openssl req -x509 -newkey rsa:2048 -keyout key.pem -out cert.pem -days 365 -nodes -subj "/CN=localhost" -addext "subjectAltName=DNS:localhost"` then `cat cert.pem key.pem > combined.pem`
  3. `socat OPENSSL-LISTEN:8000,reuseaddr,fork,cert=combined.pem,verify=0 TCP:127.0.0.1:8001`
  4. Browse `https://localhost:8000` (accept the self-signed cert warning).
  - `curl` against the app should use `-k` (self-signed) and `https://`.

### Run / lint / test
- Run everything (server + queue + logs + vite): `composer dev`. Note the Vite portion suffers the HMR/`public/hot` gotcha above; for browser testing prefer `npm run build` + the HTTPS proxy.
- Lint/format: `./vendor/bin/pint` (or `--test` to check only). `pint --test` currently reports many pre-existing style issues across the repo — that is existing code, not a broken tool.
- Tests: `php artisan test` (PHPUnit). Tests run against in-memory SQLite (`phpunit.xml`), no external services needed.
