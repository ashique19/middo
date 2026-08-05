import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Kitchen role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'kitchen');
  });

  test('dashboard and major kitchen surfaces', async ({ page }) => {
    await expect(page).toHaveURL(/\/kitchen\/dashboard/);

    await visitPaths(page, [
      '/kitchen/dashboard',
      '/kitchen/order-groups/middo',
      '/kitchen/orders/active',
      '/kitchen/menus/today',
      '/kitchen/middo-boxes/at-kitchen',
      '/kitchen/cash-handovers',
      '/kitchen/account',
      '/kitchen/complaints',
      '/kitchen/profile',
    ]);
  });
});
