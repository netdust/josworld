import { test, expect } from '@playwright/test';

/**
 * specs/translation smoke: read-only, no login, production too. The English home answers in
 * English.
 */

test('the English home answers 200 in English @smoke', async ({ request }) => {
  const home = await request.get('/en/');

  expect(home.status()).toBe(200);
  const html = await home.text();
  expect(html).toContain('<html lang="en-US"');
  expect(html).not.toMatch(/Fatal error|Parse error|Warning:<\/b>|Notice:<\/b>/);
});
