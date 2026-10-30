import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';

/**
 * specs/polylang-setup smoke (ntdst-baseline): read-only, no login, production too. Setup makes
 * drafts only, so nothing it does is public until an admin publishes; the smoke proves the command
 * is installed (where WP-CLI is reachable) and that /fr/ renders in French with no server error.
 */

test('wp ntdst polylang setup --help answers with its synopsis @smoke', async ({ baseURL }) => {
  test.skip(!new URL(baseURL ?? 'http://invalid').hostname.endsWith('.ddev.site'), 'WP-CLI is reachable through DDEV only');

  const help = execFileSync('ddev', ['wp', 'help', 'ntdst', 'polylang', 'setup'], { encoding: 'utf8' });

  expect(help).toContain('wp ntdst polylang setup <lang>... [--dry-run]');
});

test('/fr/ renders in French, with no server error @smoke', async ({ page }) => {
  const response = await page.goto('/fr/');

  expect(response?.status()).toBeLessThan(500);
  const html = await page.content();
  expect(html).not.toMatch(/Fatal error|Parse error|Warning:<\/b>|Notice:<\/b>/);
  if (response?.status() === 200) {
    expect(await page.locator('html').getAttribute('lang')).toMatch(/^fr/);
  }
});
