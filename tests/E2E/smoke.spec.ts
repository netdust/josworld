import { test, expect } from '@playwright/test';

/**
 * E2E smoke (T11): real browser, real wire, live DDEV site.
 *
 * Fixtures (e2e-admin user, e2e-fixture-post) are seeded by bin/e2e.sh via
 * wp-cli BEFORE this suite runs — never through the UI. The credential and
 * the fixture URL are single-sourced by bin/e2e.sh and arrive via env
 * (review C4 I4/I5): no password literal, no hardcoded fixture path, and no
 * site-name coupling (I6) anywhere in this spec.
 *
 * Uses plain @playwright/test rather than @wordpress/e2e-test-utils-playwright
 * fixtures: in the stack layout (rendered by scaffold_wp_starter) the admin
 * lives at /wp/wp-admin (core in app/wp), and
 * the utils' RequestUtils/Admin helpers hardcode <baseURL>/wp-admin. The utils
 * package stays installed as the documented layer for future non-path-coupled
 * helpers.
 */

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is not set — run the suite via bin/e2e.sh, which seeds and exports it.`);
  }
  return value;
}

test.describe('smoke', () => {
  test('front page renders a header with a nav landmark', async ({ page }) => {
    await page.goto('/');
    // Structural, not content-coupled (I6): a visible page header and a
    // navigation landmark — true for any sanely-built theme.
    //
    // Filter on VISIBILITY, not DOM order. YOOtheme renders a responsive PAIR
    // of headers — `tm-header-mobile` then `tm-header` — and hides one per
    // viewport via `uk-hidden@m` / `uk-visible@m`; mobile is a separate config,
    // not a media query over one header. `.first()` therefore picks the mobile
    // header, which is correctly invisible at Playwright's desktop viewport.
    await expect(page.locator('header:visible').first()).toBeVisible();
    await expect(page.locator('nav:visible').first()).toBeVisible();
  });

  test('e2e-admin can log in at /wp/wp-login.php and reach the dashboard', async ({ page }) => {
    await page.goto('/wp/wp-login.php');
    await page.locator('#user_login').fill('e2e-admin');
    await page.locator('#user_pass').fill(requiredEnv('E2E_PASS'));
    await page.locator('#wp-submit').click();
    await page.waitForURL(/\/wp\/wp-admin\//);
    await expect(page.getByRole('heading', { name: 'Dashboard' })).toBeVisible();
  });

  test('wp-cli-seeded fixture post renders at its seed-time-derived URL', async ({ page }) => {
    // URL derived by bin/e2e.sh from the seeded post itself (I5) — the spec
    // carries no assumption about the permalink structure.
    await page.goto(requiredEnv('E2E_FIXTURE_URL'));
    await expect(page.getByRole('heading', { name: 'E2E Fixture Post' })).toBeVisible();
    await expect(page.getByText('Seeded by bin/e2e.sh via wp-cli.')).toBeVisible();
  });
});
