import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { noTranslator, translatorAnswers } from './support/deepl';
import { ddevWp } from './support/mail';
import { logIn } from './support/login';

/**
 * specs/polylang-setup (ntdst-baseline): `wp ntdst polylang setup <lang>...` translates the whole
 * site into drafts. The full run spends DeepL on every post, so it is a local shake-out flow and
 * never runs here. This spec runs the dry run, the refusal, the template copies' language and one
 * real translation of a single post through the copy path setup shares with `translation create`.
 *
 * Every test needs WP-CLI through DDEV and skips on a deployed site. The single-post test sends
 * about three strings to DeepL and deletes the two posts it made.
 */

const STEPS = ['languages', 'language_packs', 'terms', 'content', 'menus', 'widgets', 'templates', 'strings', 'links'];
const HERO = `<p class="uk-text-large" x-data="typewriter({ words: ['Overheid,', 'Social profit,'] })">Wij brengen je dichter bij de mensen. <mark x-text="text"></mark></p>`;
const localOnly = 'needs WP-CLI through DDEV';

function isLocal(baseURL: string | undefined): boolean {
  return new URL(baseURL ?? 'http://invalid').hostname.endsWith('.ddev.site');
}

function wpCli(...args: string[]): { code: number; out: string } {
  try {
    return { code: 0, out: execFileSync('ddev', ['wp', ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }) };
  } catch (e) {
    const err = e as { status: number; stdout: string; stderr: string };
    return { code: err.status, out: `${err.stdout}${err.stderr}` };
  }
}

/**
 * What only setup writes: the languages, the YOOtheme option, the widget layout, the menus. Post and
 * term ids are left out: other spec files insert those in parallel; that a dry run inserts no post
 * is pinned by ntdst-baseline's own unit tests.
 */
function footprint(): string {
  return ddevWp(`echo wp_json_encode([pll_languages_list(['fields' => 'locale']), md5((string) get_option('yootheme')),
    md5(wp_json_encode(wp_get_sidebars_widgets())), count(wp_get_nav_menus())]);`);
}

test.describe('polylang setup: the dry run, the refusal and the copies @e2e', () => {
  test('setup --dry-run reports every step in order, exits 0 and writes nothing @e2e', async ({ baseURL }) => {
    test.skip(!isLocal(baseURL), localOnly);
    const before = footprint();

    const run = wpCli('ntdst', 'polylang', 'setup', 'fr', 'en', '--dry-run');

    expect(run.code).toBe(0);
    const lines = run.out.split('\n').filter((line) => /^\w+: \d+ done/.test(line));
    expect(lines.map((line) => line.split(':')[0])).toEqual(STEPS);
    for (const line of lines) {
      expect(line).toMatch(/^\w+: \d+ done, \d+ skipped, 0 failed$/);
    }
    expect(footprint()).toBe(before);
  });

  test('setup with an unknown language refuses, exits 1 and writes nothing @e2e', async ({ baseURL }) => {
    test.skip(!isLocal(baseURL), localOnly);
    const before = footprint();

    const run = wpCli('ntdst', 'polylang', 'setup', 'xx');

    expect(run.code).toBe(1);
    expect(run.out).toContain("'xx' is not one of Polylang's languages");
    expect(footprint()).toBe(before);
  });

  test('every template copy setup made is limited to a site language, sits before its original, and pins the original to the default @e2e', async ({ baseURL }) => {
    test.skip(!isLocal(baseURL), localOnly);
    const templates = JSON.parse(ddevWp(`$o = json_decode((string) get_option('yootheme'));
      $out = []; foreach ((array) ($o->templates ?? []) as $id => $t) { $out[] = ['id' => (string) $id, 'locale' => $t->query->locale ?? '', 'source' => $t->ntdst_pll_source ?? '']; }
      echo wp_json_encode($out);`)) as { id: string; locale: string; source: string }[];
    const copies = templates.filter((t) => t.source !== '');
    test.skip(copies.length === 0, 'no setup template copies on this site');
    const siteLocales = JSON.parse(ddevWp(`echo wp_json_encode(pll_languages_list(['fields' => 'locale']));`)) as string[];
    const defaultLocale = ddevWp(`echo pll_default_language('locale');`).trim();
    const position = new Map(templates.map((t, i) => [t.id, i]));

    for (const copy of copies) {
      const original = templates.find((t) => t.id === copy.source);
      expect(original, `template ${copy.id}'s original ${copy.source} is still there`).toBeDefined();
      expect(siteLocales, `template ${copy.id} is limited to ${copy.locale}`).toContain(copy.locale);
      expect(copy.locale, `template ${copy.id} is not limited to the default language`).not.toBe(defaultLocale);
      expect(original!.locale, `original ${copy.source} is pinned to the default language`).toBe(defaultLocale);
      expect(position.get(copy.id)!, `template ${copy.id} sits before its original`).toBeLessThan(position.get(copy.source)!);
    }
  });

  test('every same-site link in a FR copy setup made points at a FR URL @e2e', async ({ baseURL }) => {
    test.skip(!isLocal(baseURL), localOnly);
    // Setup repoints a layout's "link" props and its JSON-escaped href; a classic post and the plain-HTML fallback before the layout are out of scope by ruling.
    const links = JSON.parse(ddevWp(`$home = untrailingslashit(home_url()); $found = [];
      $collect = function (string $where, string $text) use (&$found, $home) {
        $text = str_replace('\\/', '/', $text);
        preg_match_all('#(?:href=\\\\\\\\"|"link":")((?:' . preg_quote($home, '#') . ')?/[^"\\\\\\\\?\\#]*)#', $text, $m);
        foreach ($m[1] as $url) { $path = substr($url, str_starts_with($url, $home) ? strlen($home) : 0);
          if (!in_array(trim($path, '/'), ['', 'en', 'fr'], true) && !str_starts_with($path, '/wp/') && !str_starts_with($path, '/fr/')) { $found[] = "$where $path"; } }
      };
      foreach (get_posts(['post_type' => 'any', 'post_status' => 'draft', 'lang' => 'fr', 'meta_key' => '_ntdst_translation_unchecked', 'meta_value' => '1', 'posts_per_page' => -1]) as $p) {
        $at = strpos($p->post_content, '<!-- {'); if ($at !== false) { $collect("post {$p->ID}", substr($p->post_content, $at)); } }
      foreach ((array) get_option('widget_builderwidget') as $n => $w) { if (is_array($w) && ($w['pll_lang'] ?? '') === 'fr' && !empty($w['ntdst_pll_source'])) { $collect("widget builderwidget-$n", (string) ($w['content'] ?? '')); } }
      echo wp_json_encode(array_values(array_unique($found)));`)) as string[];

    expect(links, 'same-site links in FR copies that still point at a Dutch URL').toEqual([]);
  });

  test('one post translated to FR keeps its hero x-data and x-text, and its typewriter runs in the preview @e2e', async ({ page, baseURL }) => {
    test.skip(!isLocal(baseURL), localOnly);
    test.skip(!translatorAnswers(), noTranslator);
    test.setTimeout(180_000);
    const source = Number(ddevWp(`$id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'E2E polylang setup bron',
      'post_content' => wp_slash(${JSON.stringify(HERO)})]); pll_set_post_language($id, pll_default_language()); echo $id;`));
    let copy = 0;

    try {
      const run = wpCli('ntdst', 'polylang', 'translation', 'create', String(source), '--lang=fr');
      expect(run.code, run.out).toBe(0);
      copy = Number(ddevWp(`echo (int) pll_get_post(${source}, 'fr');`));
      expect(copy).toBeGreaterThan(0);

      const fr = JSON.parse(ddevWp(`$p = get_post(${copy}); echo wp_json_encode(['title' => $p->post_title, 'status' => $p->post_status,
        'content' => $p->post_content, 'unchecked' => get_post_meta(${copy}, '_ntdst_translation_unchecked', true)]);`)) as { title: string; status: string; content: string; unchecked: string };
      expect(fr.status).toBe('draft');
      expect(fr.unchecked).toBe('1');
      expect(fr.title, 'the title came back translated').not.toBe('E2E polylang setup bron');
      // wp_kses_post writes the quote as &apos;; the browser reads the same attribute value, checked below.
      expect(fr.content).toContain('x-data="typewriter(');
      expect(fr.content).toContain('x-text="text"');

      await logIn(page, 'e2e-admin');
      await page.goto(`/?p=${copy}&preview=true`);
      await expect(page.locator('[x-data^="typewriter"]')).toHaveAttribute('x-data', "typewriter({ words: ['Overheid,', 'Social profit,'] })");
      const typed = page.locator('[x-data^="typewriter"] mark[x-text="text"]');
      const first = await typed.innerText();
      await expect.poll(async () => typed.innerText(), { message: 'the typewriter types', timeout: 10_000 }).not.toBe(first);
    } finally {
      ddevWp(`foreach ([${source}, ${copy}] as $id) { if ($id) { wp_delete_post($id, true); } }`);
    }
  });
});
