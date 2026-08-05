# Playwright E2E

Seeded logins (password `12345678`) use mobile numbers from `UserSeeder`.

## Setup

```powershell
npm run build
Remove-Item public/hot -ErrorAction SilentlyContinue
php artisan migrate:fresh --seed
$env:FORCE_HTTPS = 'false'
php artisan serve --host=127.0.0.1 --port=8000
```

In another terminal:

```powershell
npm run test:e2e
```

Videos land in `tests/e2e/recordings/` (gitignored). HTML report: `tests/e2e/playwright-report/`.

`npm run test:e2e` runs `E2eOrderLifecycleSeeder` via Playwright `globalSetup` before specs.

Re-seed only the lifecycle fixture manually:

```powershell
php artisan db:seed --class=E2eOrderLifecycleSeeder
```
