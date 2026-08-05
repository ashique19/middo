import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Corporate role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'corporate');
  });

  test('dashboard packages scheduled history wallet profile', async ({ page }) => {
    await expect(page).toHaveURL(/\/corporate\/dashboard/);

    await visitPaths(page, [
      '/corporate/dashboard',
      '/corporate/packages',
      '/corporate/orders/scheduled',
      '/corporate/orders/history',
      '/corporate/wallet',
      '/corporate/profile',
    ]);
  });
});
