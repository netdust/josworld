import { test, expect } from '@playwright/test';

/**
 * C3 · Diensten — kaarten: the three cards stack on top of each other while
 * scrolling (each card sticks within the section at a stepped top offset),
 * then the page continues once the section ends. Built with YOOtheme's
 * column "Position Sticky: Column within Section" — no custom JS.
 */
const STACK_PAGES = ['/voor-overheden/', '/voor-social-profit/'];

for (const path of STACK_PAGES) {
  test(`Diensten cards stack while scrolling on ${path}`, async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto(path);

    const cards = page.locator('[uk-sticky*="end: !.uk-section"]');
    await expect(cards).toHaveCount(3);

    const offsets = await cards.evaluateAll((els) =>
      els.map((el) => parseInt(el.getAttribute('uk-sticky')!.match(/offset:\s*(\d+)/)![1], 10)));
    expect(offsets[0]).toBeLessThan(offsets[1]);
    expect(offsets[1]).toBeLessThan(offsets[2]);

    // Scroll the last card to its resting offset: the first two must be
    // pinned above it at their own offsets, i.e. stacked.
    await cards.nth(2).evaluate((el, top) => {
      window.scrollTo(0, window.scrollY + el.getBoundingClientRect().top - top);
    }, offsets[2]);
    await page.waitForTimeout(300);

    for (let i = 0; i < 3; i++) {
      const box = await cards.nth(i).boundingBox();
      expect(Math.round(box!.y)).toBe(offsets[i]);
    }
  });
}
