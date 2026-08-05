import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs, visitPaths } from './helpers/auth';

test.describe('Operation role scenarios', () => {
  test.beforeEach(async ({ page }) => {
    autoAcceptDialogs(page);
    await loginAs(page, 'operation');
  });

  test('dashboard and major operation surfaces', async ({ page }) => {
    await expect(page).toHaveURL(/\/operation\/dashboard/);

    await visitPaths(page, [
      '/operation/dashboard',
      '/operation/corporates',
      '/operation/kitchens',
      '/operation/orders/active',
      '/operation/orders/history',
      '/operation/sla',
      '/operation/riders',
      '/operation/coverage',
      '/operation/cash-handovers',
      '/operation/middo-boxes',
    ]);
  });
});
