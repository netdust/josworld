import { test, expect } from '@playwright/test';

/**
 * A domain archive is the Ons werk archive filtered: the term's name as the heading, the cases as
 * cards — not the default template, which printed every case's full content (a 40,000px page).
 * The term comes from the first domain pill on Ons werk (its link is /ons-werk/<domain>), so no fixed slug.
 */
test.describe('domain archive @e2e', () => {
  test('a domain pill leads to Ons werk, filtered, as cards', async ({ page }) => {
    await page.goto('/ons-werk/');
    const pill = page.locator('a[rel="tag"][href*="/ons-werk/"]').first();
    const term = (await pill.innerText()).trim();
    await Promise.all([
      page.waitForURL(/\/ons-werk\/[^/]+\/$/),
      pill.evaluate((a: HTMLElement) => a.click()),
    ]);

    expect(new URL(page.url()).pathname, 'the overview lives under Ons werk').toMatch(/^\/ons-werk\/[^/]+\/$/);

    await expect(page.locator('h1, h2').filter({ hasText: term }).first()).toBeVisible();
    expect(await page.locator('main .el-title').count(), 'the term\'s cases').toBeGreaterThan(0);
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    expect(height, 'cards, not every case page stacked').toBeLessThan(12000);
  });
});
