import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Delivery role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'delivery');
  });

  test('dashboard and major delivery surfaces', async ({ page }) => {
    await expect(page).toHaveURL(/\/delivery\/dashboard/);

    await visitPaths(page, [
      '/delivery/dashboard',
      '/delivery/kitchen-dispatches',
      '/delivery/orders/delivered',
      '/delivery/middo-boxes/pending-run',
      '/delivery/cash-handovers',
      '/delivery/custom-runs',
      '/delivery/account',
    ]);
  });
});
