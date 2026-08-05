import { type Page, expect } from '@playwright/test';

export const ACCOUNTS = {
  admin: { mobile: '01310123451', password: '12345678' },
  corporate: { mobile: '01310123452', password: '12345678' },
  kitchen: { mobile: '01310123453', password: '12345678' },
  delivery: { mobile: '01310123454', password: '12345678' },
  operation: { mobile: '01310123455', password: '12345678' },
  accounts: { mobile: '01310123462', password: '12345678' },
} as const;

export type RoleKey = keyof typeof ACCOUNTS;

/** Accept Livewire wire:confirm / browser dialogs automatically. */
export function autoAcceptDialogs(page: Page): void {
  page.on('dialog', async (dialog) => {
    await dialog.accept();
  });
}

export async function loginAs(page: Page, role: RoleKey): Promise<void> {
  const { mobile, password } = ACCOUNTS[role];
  await page.goto('/login');
  await page.locator('input[name="mobile"]').fill(mobile);
  await page.locator('input[name="password"]').fill(password);
  await page.getByRole('button', { name: /sign in/i }).click();
  await page.waitForURL((url) => !url.pathname.includes('/login'), { timeout: 20_000 });
}

export async function expectPageOk(page: Page, path: string, heading?: RegExp | string): Promise<void> {
  const response = await page.goto(path);
  expect(response?.ok() ?? false, `Expected OK for ${path}`).toBeTruthy();
  await expect(page.locator('body')).toBeVisible();
  if (heading) {
    await expect(page.getByText(heading).first()).toBeVisible();
  }
}

export async function visitPaths(page: Page, paths: string[]): Promise<void> {
  for (const path of paths) {
    const response = await page.goto(path);
    expect(response?.ok() ?? false, `Expected OK for ${path}`).toBeTruthy();
    await expect(page.locator('body')).toBeVisible();
  }
}
