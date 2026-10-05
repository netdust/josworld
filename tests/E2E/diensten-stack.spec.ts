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

const VIEWPORTS = { desktop: { width: 1440, height: 900 }, phone: { width: 390, height: 844 } };

for (const [path, count] of Object.entries(STACK_PAGES)) for (const [device, viewport] of Object.entries(VIEWPORTS)) {
  test(`cards stack while scrolling on ${path} (${device}) @smoke @e2e`, async ({ page }) => {
    await page.setViewportSize(viewport);
    await page.goto(path);

    const cards = page.locator('[uk-sticky*="end: !.uk-section"]');
    await expect(cards).toHaveCount(count);

    // Review 2026-09-18: cards pin near the TOP (offset 48px) rather than
    // centred, and consecutive cards are an xlarge margin apart. The gap is
    // what decides how long a card's button stays readable before the next
    // card slides up over it — at the old 32px it vanished almost at once.
    const gaps = await cards.evaluateAll((els) => els.slice(1).map((el, i) =>
      el.getBoundingClientRect().top - els[i].getBoundingClientRect().bottom));
    for (const gap of gaps) expect(gap).toBeGreaterThanOrEqual(64);

    // Read the pin while the FIRST card is genuinely held: scroll a little
    // past the point where it sticks, with the rest of the stack still below.
    // (At the last card the section's end is already releasing the whole
    // stack — `end: !.uk-section` — so every card is being pushed up there.)
    const pinned = await cards.first().evaluate(async (el) => {
      const abs = el.getBoundingClientRect().top + window.scrollY;
      window.scrollTo(0, abs - 48 + 80);
      await new Promise((r) => setTimeout(r, 300));
      return Math.round(el.getBoundingClientRect().top);
    });
    expect(pinned).toBe(48);
  });
}
