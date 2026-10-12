import { test, expect } from '@playwright/test';

/**
 * Phone-width layout of three builder sections on the doelgroep pages
 * (reported 2026-09-10): the contact photo in A3 shrinks and centres, the tool
 * card in A5 stays on top of the sun, and the review slider in C5 keeps its
 * gutter instead of bleeding to the screen edge.
 */
const box = (el: Element) => el.getBoundingClientRect();

test.describe('phone layout @smoke @e2e', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  for (const path of ['/voor-overheden/', '/voor-social-profit/']) {
    test(`A3 photo, A5 sun card and C5 slider on ${path}`, async ({ page }) => {
      await page.goto(path);

      const photo = page.locator('img.uk-border-circle[width="120"]:visible').last();
      const pb = (await photo.boundingBox())!;
      expect(pb.width).toBeLessThanOrEqual(120);
      expect(Math.round(pb.x + pb.width / 2)).toBe(195);

      const overlap = await page.locator('.jw-tile-card').first().evaluate((col) => {
        const shown = (sel: string) => [...col.querySelectorAll<HTMLElement>(sel)].find((i) => i.offsetParent)!;
        const s = shown('img[src*="sun"]').getBoundingClientRect();
        const c = shown('img:not([src*="sun"])').getBoundingClientRect();
        return Math.min(s.bottom, c.bottom) - Math.max(s.top, c.top);
      });
      expect(overlap).toBeGreaterThanOrEqual(200);

      const card = page.locator('.jw-review .uk-slider-items > * > *').first();
      const cb = (await card.boundingBox())!;
      expect(cb.x).toBeGreaterThanOrEqual(24);
      expect(cb.x + cb.width).toBeLessThanOrEqual(390 - 24);
    });
  }
});
