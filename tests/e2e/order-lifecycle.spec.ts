import { test, expect } from '@playwright/test';
import { autoAcceptDialogs, loginAs } from './helpers/auth';
import { E2E_BOX_QR, E2E_GROUP_NAME } from './helpers/seed';

/**
 * Cross-role happy path using E2eOrderLifecycleSeeder fixture:
 * kitchen accept → mark ready → dispatch → rider pick up → deliver.
 */
test.describe('Order lifecycle', () => {
  test('kitchen packs and rider delivers E2E-LIFECYCLE order', async ({ page }) => {
    autoAcceptDialogs(page);

    // --- Kitchen: accept group ---
    await loginAs(page, 'kitchen');
    await page.goto('/kitchen/order-groups/middo');
    await expect(page.getByText(E2E_GROUP_NAME).first()).toBeVisible({ timeout: 20_000 });

    const poolCard = page.locator('article').filter({ hasText: E2E_GROUP_NAME }).first();
    await poolCard.getByRole('button', { name: /accept group/i }).click();
    await page.waitForTimeout(1500);

    // --- Kitchen: mark ready ---
    await page.goto('/kitchen/orders/active');
    const activeCard = page.locator('article').filter({ hasText: E2E_GROUP_NAME }).first();
    await expect(activeCard).toBeVisible({ timeout: 20_000 });

    const markGroupReady = activeCard.getByRole('button', { name: /mark group ready/i });
    if (await markGroupReady.count()) {
      await markGroupReady.click();
    } else {
      await activeCard.getByRole('button', { name: /mark ready/i }).click();
    }
    await expect(activeCard.getByRole('button', { name: /^dispatch$/i })).toBeVisible({ timeout: 20_000 });

    // --- Kitchen: dispatch with Middo box ---
    await activeCard.getByRole('button', { name: /^dispatch$/i }).click();
    await expect(page.getByRole('heading', { name: /dispatch order/i })).toBeVisible();
    await page.getByText(E2E_BOX_QR).first().click();
    await page.getByRole('button', { name: /confirm dispatch/i }).click();
    await expect(activeCard.getByText(/dispatched/i)).toBeVisible({ timeout: 20_000 });

    // --- Logout kitchen / login delivery ---
    await page.goto('/logout');
    await loginAs(page, 'delivery');

    await page.goto('/delivery/kitchen-dispatches');
    const dispatchCard = page.locator('div.bg-white').filter({ hasText: E2E_BOX_QR }).first();
    await expect(dispatchCard).toBeVisible({ timeout: 20_000 });
    await dispatchCard.getByRole('button', { name: /pick up packed order/i }).click();
    await expect(dispatchCard.getByRole('button', { name: /^delivered$/i })).toBeVisible({ timeout: 20_000 });
    await dispatchCard.getByRole('button', { name: /^delivered$/i }).click();

    await page.waitForTimeout(1500);
    await expect(page.getByText(/delivered order|boxes are now with the customer/i).first()).toBeVisible({ timeout: 15_000 });

    await page.goto('/delivery/orders/delivered');
    await expect(page.locator('body')).toContainText(/Delivered|order/i);
  });
});
