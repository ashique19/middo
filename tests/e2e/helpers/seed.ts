/**
 * E2E prerequisites (run before Playwright):
 *
 *   npm run build
 *   # remove public/hot if present
 *   php artisan migrate:fresh --seed
 *   $env:FORCE_HTTPS='false'; php artisan serve --host=127.0.0.1 --port=8000
 *
 * Lifecycle fixture tag: order group name `E2E-LIFECYCLE` (see E2eOrderLifecycleSeeder).
 */
export const E2E_GROUP_NAME = 'E2E-LIFECYCLE';
export const E2E_BOX_QR = 'MB-E2E-01';
