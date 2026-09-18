import { test, expect } from '@playwright/test';

/**
 * Site motion (specs/site-motion): YOOtheme's section animation, one rhythm —
 * "slide-bottom-medium", 150ms between elements. The hero never waits on a scroll.
 */
const RHYTHM = '[uk-scrollspy*="slide-bottom-medium"]';

test.describe('site motion (desktop)', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test('homepage sections cascade in, in order', async ({ page }) => {
    await page.goto('/');
    // the homepage's second section is the hero photo; take the first one with a heading, text and button
    const section = page.locator(RHYTHM).filter({ has: page.locator('.uk-button') }).first();
    await expect(section).toHaveAttribute('uk-scrollspy', /delay: 150/);

    await section.evaluate((el) => {
      const seen: Record<string, number> = {};
      (window as any).__inview = seen;
      const kind = (t: Element) => (/^H[1-6]$/.test(t.tagName) ? 'heading' : t.querySelector('.uk-button') ? 'button' : 'text');
      new MutationObserver((records) => {
        for (const r of records) {
          const t = r.target as Element;
          if (t.classList.contains('uk-scrollspy-inview')) seen[kind(t)] ??= performance.now();
        }
      }).observe(el, { subtree: true, attributes: true, attributeFilter: ['class'] });
    });
    await section.scrollIntoViewIfNeeded();
    await page.waitForFunction(() => (window as any).__inview.button);

    const at = await page.evaluate(() => (window as any).__inview);
    expect(at.text - at.heading).toBeGreaterThanOrEqual(100);
    expect(at.button - at.text).toBeGreaterThanOrEqual(100);
  });

  test('the hero is visible at once and carries no section animation', async ({ page }) => {
    await page.goto('/');
    const hero = page.locator('main .uk-section').first();
    await expect(hero).not.toHaveAttribute('uk-scrollspy', /.*/);
    await expect(page.locator('h1')).toBeVisible();
  });
});
