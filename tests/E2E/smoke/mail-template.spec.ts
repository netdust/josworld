import { test, expect } from '@playwright/test';

/**
 * specs/mail-template smoke: read-only, no login, production too. Every mail the site sends
 * shows this logo, fetched from the environment that sent it.
 */

test('the logo every mail points to loads @smoke', async ({ request }) => {
  const logo = await request.get('/content/themes/josworld/images/logo.png');

  expect(logo.status()).toBe(200);
  expect(logo.headers()['content-type']).toBe('image/png');
  expect((await logo.body()).subarray(0, 8)).toEqual(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]));
});
