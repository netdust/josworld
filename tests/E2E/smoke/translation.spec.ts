import { test, expect } from '@playwright/test';

/**
 * specs/translation smoke: read-only, no login, production too. /en/ answers in English. Its
 * status is content: 404 until Jos publishes an English home (trashed on staging and production).
 */

test('/en/ answers in English, with no server error @smoke', async ({ request }) => {
  const home = await request.get('/en/');

  expect(home.status()).toBeLessThan(500);
  const html = await home.text();
  expect(html).toContain('<html lang="en-US"');
  expect(html).not.toMatch(/Fatal error|Parse error|Warning:<\/b>|Notice:<\/b>/);
});
