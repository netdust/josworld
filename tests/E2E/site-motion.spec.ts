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
    await expect(section).toHaveAttribute('uk-scrollspy', /delay: 150;/);
    // below the fold at load, or the observer below would connect after the fact
    await expect(section.locator('.uk-scrollspy-inview')).toHaveCount(0);

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

test.describe('site motion, the rest of the site', () => {
  test('every eligible section across the site carries the one rhythm', async ({ page }) => {
    for (const path of ['/waarom-jos/', '/ons-werk/onze-energie/', '/jobs-stages/']) {
      await page.goto(path);
      const without = await page.locator('main .uk-section').evaluateAll((sections) =>
        sections
          .slice(1)
          .filter((s) => !s.querySelector('.jw-logo-wall') && !s.querySelector('[uk-sticky]'))
          .filter((s) => !/slide-bottom-medium.*delay: 150;/.test(s.closest('[uk-scrollspy]')?.getAttribute('uk-scrollspy') ?? ''))
          .length);
      expect(without, path).toBe(0);
    }
  });
});

const hiddenOrMoved = (els: Element[]) =>
  els.filter((el) => {
    const cs = getComputedStyle(el);
    return cs.opacity !== '1' || cs.transform !== 'none' || cs.animationName !== 'none';
  }).length;

test.describe('site motion, reduced motion requested', () => {
  test.use({ viewport: { width: 1440, height: 900 }, contextOptions: { reducedMotion: 'reduce' } });

  test('every animated element is visible, in place, without scrolling', async ({ page }) => {
    await page.goto('/');
    const targets = page.locator('[uk-scrollspy-class]');
    expect(await targets.count()).toBeGreaterThan(10);
    expect(await targets.evaluateAll(hiddenOrMoved)).toBe(0);
  });
});

test.describe('site motion, JavaScript unavailable', () => {
  test.use({ javaScriptEnabled: false });

  test('nothing carrying the animation hook is hidden', async ({ page }) => {
    await page.goto('/');
    const targets = page.locator('[uk-scrollspy-class]');
    expect(await targets.count()).toBeGreaterThan(10);
    expect(await targets.evaluateAll(hiddenOrMoved)).toBe(0);
  });
});
