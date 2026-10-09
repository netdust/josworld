import { test, expect } from '@playwright/test';

/**
 * A domain archive is the Ons werk archive filtered: the term's name as the heading, the cases as
 * cards — not the default template, which printed every case's full content (a 40,000px page).
 * The term comes from the first domain pill on Ons werk, so the check needs no fixed slug.
 */
test.describe('domain archive @e2e', () => {
  test('a domain pill leads to Ons werk, filtered, as cards', async ({ page }) => {
    await page.goto('/ons-werk/');
    const pill = page.locator('a[rel="tag"][href*="/domein/"]').first();
    const term = (await pill.innerText()).trim();
    await pill.evaluate((a: HTMLElement) => a.click());
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1, h2').filter({ hasText: term }).first()).toBeVisible();
    expect(await page.locator('main .el-title').count(), 'the term\'s cases').toBeGreaterThan(0);
    const height = await page.evaluate(() => document.documentElement.scrollHeight);
    expect(height, 'cards, not every case page stacked').toBeLessThan(12000);
  });
});
