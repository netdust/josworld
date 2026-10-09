import { test, expect } from '@playwright/test';

/**
 * What a crawler, a link preview and a view-source see: the WordPress fingerprint gone,
 * the SEO tags present and true. Reads the SERVED response (markup + headers), not the DOM.
 * ntdst-baseline supplies the tags; josworld-core supplies the site's own values.
 */
const BASELINE = 'We brengen je dichter bij de mensen.';
const FACEBOOK = 'https://www.facebook.com/josworldbrussels/';
const INSTAGRAM = 'https://www.instagram.com/josworld.agency/';

const meta = (html: string, attr: 'name' | 'property', key: string) =>
  html.match(new RegExp(`<meta ${attr}=["']${key}["'] content=["']([^"']*)["']`))?.[1];

test.describe('served HTML: the homepage @e2e', () => {
  let html = '';
  let headers: Record<string, string> = {};

  test.beforeAll(async ({ request }) => {
    const res = await request.get('/');
    html = await res.text();
    headers = res.headers();
  });

  test('no WordPress block styles, migrate script or generator fingerprint', () => {
    for (const leftover of ['wp-block-library', 'global-styles-inline-css', 'classic-theme-styles', 'core-block-supports', 'jquery-migrate', 'wp-emoji', 'name="generator"']) {
      expect(html, leftover).not.toContain(leftover);
    }
  });

  test('no REST or shortlink advertised in the response headers', () => {
    expect(headers['link'] ?? '').not.toMatch(/wp-json|shortlink|api\.w\.org/);
  });

  test('the body classes carry no page ID or theme fingerprint', () => {
    const classes = html.match(/<body class="([^"]*)"/)?.[1] ?? '';
    expect(classes).not.toMatch(/wp-singular|page-id-|postid-|wp-theme-|wp-child-theme-|wp-embed-responsive|page-template/);
  });

  test('one canonical, and the title carries the baseline', () => {
    expect(html.match(/rel=["']canonical["']/g)).toHaveLength(1);
    expect(html.match(/<title>([\s\S]*?)<\/title>/)?.[1]).toContain(BASELINE);
  });

  test('the description is the baseline text', () => {
    expect(meta(html, 'name', 'description')).toBe(BASELINE);
    expect(meta(html, 'property', 'og:description')).toBe(BASELINE);
  });

  test('link previews get a website type and the logo image', () => {
    expect(meta(html, 'property', 'og:type')).toBe('website');
    expect(meta(html, 'property', 'og:image')).toMatch(/\/images\/og-default\.png$/);
    expect(meta(html, 'name', 'twitter:card')).toBe('summary_large_image');
  });

  test('structured data names the business, its logo and its profiles', () => {
    const blocks = [...html.matchAll(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/g)].map((m) => JSON.parse(m[1]));
    const org = blocks.flatMap((b) => (Array.isArray(b['@graph']) ? b['@graph'] : [b])).find((n) => /Service|Business|Organization/.test(String(n['@type'])));
    expect(org, 'an organisation node').toBeTruthy();
    expect(org.name).toBe('Jos');
    expect(org.logo).toMatch(/\/images\/logo\.png$/);
    expect(org.sameAs).toEqual([FACEBOOK, INSTAGRAM]);
    expect(org.address?.postalCode).toBe('1000');
  });

  test('a favicon and a touch icon are declared', () => {
    expect(html).toMatch(/rel=["']icon["'][^>]*icon-32\.png/);
    expect(html).toMatch(/rel=["']apple-touch-icon["'][^>]*icon-180\.png/);
  });

  test('outside production the site asks not to be indexed', () => {
    expect(meta(html, 'name', 'robots')).toMatch(/noindex/);
  });
});

test.describe('served HTML: other pages @e2e', () => {
  test('a page describes itself block by block, not glued together', async ({ request }) => {
    const html = await (await request.get('/waarom-jos/')).text();
    const description = meta(html, 'name', 'description') ?? '';
    expect(description.length).toBeGreaterThan(40);
    expect(description).not.toMatch(/[a-z][.!?][A-Z]/);
    expect(description).not.toMatch(/\[|<|&lt;/);
  });

  test('a blog post is an article, a page is not', async ({ request }) => {
    const post = await (await request.get(process.env.E2E_FIXTURE_URL ?? '/waarom-we-een-cooperatie-werden/')).text();
    expect(meta(post, 'property', 'og:type')).toBe('article');
  });
});

const LISTINGS = ['/ons-werk/', '/team/', '/jobs-stages/', '/gratis-aanbod/', '/blog-nieuws/'];

test.describe('served HTML: listings, icons and share images @e2e', () => {
  for (const path of LISTINGS) {
    test(`${path} has one canonical, its own description and a matching og:title`, async ({ request, baseURL }) => {
      const html = await (await request.get(path)).text();
      expect(html.match(/rel=["']canonical["']/g), 'canonical').toHaveLength(1);
      expect(html.match(/rel=["']canonical["'] href=["']([^"']*)/)?.[1]).toBe(new URL(path, baseURL).toString());
      expect((meta(html, 'name', 'description') ?? '').length, 'description').toBeGreaterThan(40);
      expect(meta(html, 'property', 'og:url')).toBe(new URL(path, baseURL).toString());
      expect(meta(html, 'property', 'og:title')).not.toBe('Jos');
      expect(meta(html, 'property', 'og:locale')).toBe('nl_BE');
    });
  }

  test('the front page is titled by the site, not by its page title', async ({ request }) => {
    expect(meta(await (await request.get('/')).text(), 'property', 'og:title')).toBe('Jos');
  });

  test('the share image has real dimensions', async ({ request }) => {
    const html = await (await request.get('/')).text();
    expect(meta(html, 'property', 'og:image:width')).toBe('1200');
    expect(meta(html, 'property', 'og:image:height')).toBe('630');
  });

  test('a case and a team member describe themselves, not the template', async ({ request }) => {
    for (const path of ['/ons-werk/onze-energie/', '/team/goele-van-dam/']) {
      const description = meta(await (await request.get(path)).text(), 'name', 'description') ?? '';
      expect(description, path).not.toMatch(/Page title over two lines|Lorem ipsum|Intro paragraph/);
      expect(description.length, path).toBeGreaterThan(40);
    }
  });

  test('the icon set and the manifest are served', async ({ request }) => {
    const html = await (await request.get('/')).text();
    expect(html).toMatch(/rel=["']icon["'] href=["'][^"']*favicon\.ico["']/);
    expect(html).toMatch(/rel=["']manifest["'] href=["'][^"']*site\.webmanifest["']/);
    expect(meta(html, 'name', 'theme-color')).toBe('#FFF794');

    for (const file of ['favicon.ico', 'icon/icon-32.png', 'icon/icon-180.png', 'icon/icon-192.png', 'icon/icon-270.png', 'icon/icon-512.png', 'site.webmanifest']) {
      const res = await request.get(`/content/themes/josworld/images/${file}`);
      expect(res.status(), file).toBe(200);
    }
    const manifest = await (await request.get('/content/themes/josworld/images/site.webmanifest')).json();
    expect(manifest.icons).toHaveLength(2);
    expect(html).toMatch(/rel=["']icon["'][^>]*icon-192\.png/);
  });
});
