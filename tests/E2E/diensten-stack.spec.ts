import { test, expect } from '@playwright/test';

/**
 * C3 · Diensten — kaarten: the three cards stack on top of each other while
 * scrolling (each card sticks within the section, vertically centered on the screen),
 * then the page continues once the section ends. Built with YOOtheme's
 * column "Position Sticky: Column within Section" — no custom JS.
 */
const STACK_PAGES: Record<string, number> = {
  '/voor-overheden/': 3, '/voor-social-profit/': 3, // C3 · Diensten
  '/waarom-jos/': 4, '/samenwerken/': 4, // C11 · Kaartenstapel
};

for (const [path, count] of Object.entries(STACK_PAGES)) {
  test(`cards stack while scrolling on ${path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(path);

    const cards = page.locator('[uk-sticky*="end: !.uk-section"]');
    await expect(cards).toHaveCount(count);

    // Every card rests with its middle at the viewport middle (offset
    // "50vh - 50%"): scroll the last card to that spot and the first two
    // must be pinned underneath it at the very same position.
    await cards.nth(count - 1).evaluate((el) => {
      const r = el.getBoundingClientRect();
      window.scrollTo(0, window.scrollY + r.top - (window.innerHeight - r.height) / 2);
    });
    await page.waitForTimeout(300);

    const last = (await cards.nth(count - 1).boundingBox())!;
    expect(Math.round(last.y + last.height / 2)).toBe(450);

    // Earlier cards are covered by the last one; a taller card may peek out
    // by its extra height once the section end pushes it up.
    for (let i = 0; i < count - 1; i++) {
      const box = (await cards.nth(i).boundingBox())!;
      const overlap = Math.min(box.y + box.height, last.y + last.height) - Math.max(box.y, last.y);
      expect(overlap).toBeGreaterThanOrEqual(last.height);
    }
  });
}
