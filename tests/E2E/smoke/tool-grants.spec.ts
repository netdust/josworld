import { test, expect } from '@playwright/test';

/**
 * specs/tool-grants smoke: read-only, no login, production too. A token nobody was given answers the
 * Jos dead page with 404 and the three headers every personal link carries (no-referrer, no-store, noindex).
 */

const DEAD = `/toegang/${'0'.repeat(64)}/`;

test('a made-up personal link answers 404 with the dead page and the link headers @smoke', async ({ request }) => {
  const answer = await request.get(DEAD, { maxRedirects: 0 });

  expect(answer.status()).toBe(404);
  const headers = answer.headers();
  expect(headers['referrer-policy']).toBe('no-referrer');
  expect(headers['cache-control']).toContain('no-store');
  expect(headers['cache-control']).toContain('private');
  expect(headers['x-robots-tag']).toContain('noindex');

  const html = await answer.text();
  expect(html).toContain('Deze link werkt niet meer');
  expect(html).not.toMatch(/Fatal error|Parse error|Warning:<\/b>|Notice:<\/b>/);
});

test('the same link without its trailing slash answers 404 too, not a redirect @smoke', async ({ request }) => {
  const answer = await request.get(DEAD.replace(/\/$/, ''), { maxRedirects: 0 });

  expect(answer.status()).toBe(404);
  expect(answer.headers()['referrer-policy']).toBe('no-referrer');
});
