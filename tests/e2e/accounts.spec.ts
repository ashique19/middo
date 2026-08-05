import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Accounts role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'accounts');
  });

  test('dashboard and major accounts money surfaces', async ({ page }) => {
    await expect(page).toHaveURL(/\/accounts\/dashboard/);

    await visitPaths(page, [
      '/accounts/dashboard',
      '/accounts/accounts',
      '/accounts/middo-cash',
      '/accounts/cash-handovers',
      '/accounts/cod-recon',
      '/accounts/kitchen-money',
      '/accounts/rider-money',
      '/accounts/corporates',
    ]);
  });
});
