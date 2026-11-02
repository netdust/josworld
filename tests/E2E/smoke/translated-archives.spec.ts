import { test, expect } from '@playwright/test';

/**
 * specs/polylang-en-archives smoke: read-only, no login, production too. Each archive answers on
 * its own language's word; the English word served the empty placeholder page before.
 */

const archives = [
  { type: 'case', nl: '/ons-werk/', en: '/en/our-work/' },
  { type: 'team', nl: '/team/', en: '/en/team/' },
  { type: 'vacature', nl: '/jobs-stages/', en: '/en/jobs-internships/' },
  { type: 'tool', nl: '/gratis-aanbod/', en: '/en/free-offer/' },
];

for (const { type, nl, en } of archives) {
  test(`${en} and ${nl} are the ${type} archive @smoke`, async ({ request }) => {
    for (const path of [nl, en]) {
      const response = await request.get(path, { maxRedirects: 0 });

      expect(response.status(), path).toBe(200);
      expect(await response.text(), path).toMatch(new RegExp(`<body[^>]*class="[^"]*post-type-archive-${type}[\\s"]`));
    }
  });
}

test('an English case links under /en/our-work/ @smoke', async ({ request }) => {
  const html = await (await request.get('/en/our-work/')).text();
  const links = [...html.matchAll(/href="[^"]*\/en\/(our-work|ons-werk)\/[a-z0-9-]+\/"/g)].map((m) => m[1]);

  expect(links.length).toBeGreaterThan(0);
  expect(links.every((word) => word === 'our-work')).toBe(true);
});
