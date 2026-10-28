import { expect, type Page } from '@playwright/test';
import { mkdirSync, rmSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const LOCK = join(tmpdir(), 'josworld-e2e-login.lock');
const STALE_MS = 60_000;

/**
 * WordPress keeps every session of a user in one usermeta row and rewrites it on each login, so
 * workers logging in as the same account at once lose each other's tokens (the next page redirects
 * to wp-login?reauth=1). Logins queue here, across workers, on a lock directory.
 */
async function serialised<T>(work: () => Promise<T>): Promise<T> {
  for (;;) {
    try {
      mkdirSync(LOCK);
      break;
    } catch {
      try {
        if (Date.now() - statSync(LOCK).mtimeMs > STALE_MS) {
          rmSync(LOCK, { recursive: true, force: true });
        }
      } catch {
        // the holder released it between our two calls
      }
      await new Promise((resolve) => setTimeout(resolve, 50));
    }
  }
  try {
    return await work();
  } finally {
    rmSync(LOCK, { recursive: true, force: true });
  }
}

export function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is not set — run the suite via bin/e2e.sh, which seeds and exports it.`);
  }
  return value;
}

/** Logs the seeded actor in through wp-login and lands on an authenticated wp-admin screen. */
export async function logIn(page: Page, user: string): Promise<void> {
  await serialised(async () => {
    await page.goto('/wp/wp-login.php', { waitUntil: 'load' });
    // wp-login's autofocus runs after load and moves focus to the username field; fill() types into whichever field has focus, so wait for it first.
    await expect(page.locator('#user_login')).toBeFocused();
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(requiredEnv('E2E_PASS'));
    await page.locator('#wp-submit').click();
    await page.waitForURL(/\/wp\/wp-admin\//, { timeout: 40_000 });
    await expect(page.locator('#wpadminbar')).toBeVisible();
  });
}
