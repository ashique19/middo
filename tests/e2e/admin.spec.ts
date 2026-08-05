import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Admin role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'admin');
  });

  test('dashboard and major admin surfaces', async ({ page }) => {
    await expect(page).toHaveURL(/\/admin\/dashboard/);

    await visitPaths(page, [
      '/admin/dashboard',
      '/admin/users/operation',
      '/admin/users/kitchen',
      '/admin/kitchens/onboarding',
      '/admin/orders/active',
      '/admin/coupons',
      '/admin/charges',
      '/admin/settings',
    ]);

    await expect(page.locator('body')).toContainText(/Settings|setting|Accept window/i);
  });
});
