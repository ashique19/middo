import { execSync } from 'node:child_process';

/**
 * Reset the E2E-LIFECYCLE fixture so order-lifecycle.spec.ts is idempotent.
 */
export default function globalSetup(): void {
  execSync('php artisan db:seed --class=E2eOrderLifecycleSeeder --force', {
    cwd: process.cwd(),
    stdio: 'inherit',
    env: process.env,
  });
}
