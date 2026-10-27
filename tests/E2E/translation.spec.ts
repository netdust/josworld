import { test, expect, type Page } from '@playwright/test';
import { ddevWp } from './support/mail';

/**
 * specs/translation: Polylang's "+" opens a DeepL draft flagged "Unchecked"; "+" on a dienst opens
 * the add-term form filled by DeepL; unpublishing a Dutch record drafts its published copies.
 *
 * LOCAL ONLY. Every "+" here spends the real DeepL key and leaves drafts, so no title carries @e2e
 * or @smoke; the spec refuses any site but the DDEV one `ddev wp` answers for. Its Dutch team and
 * case records and the French dienst are made here through WP-CLI, and afterAll deletes every
 * post and dienst term the run created. Existing Dutch content is read, never changed.
 */

const PAGE_ID = 12; // "Waarom Jos?", a YOOtheme layout page
const PAGE_DUTCH = 'Anders dan de rest';
const AUTHOR_POST_ID = 279; // an administrator's Dutch post
const DIENST_TRANSLATED = 23; // Strategie: gets its French term by hand in flow 3
const DIENST_UNTRANSLATED = 27; // Campagne: no French term
const TEAM_NAME = 'E2E Zoë Ångström-Nuñez';
const TEAM_PHONE = '+32 470 12 34 56';
const COPY_PHONE = '+32 470 99 88 77';
const CASE_CLIENT = 'Stad Gent E2E';
const FR_DIENST = 'Stratégie E2E';

type Ids = { posts: number; terms: number };
let start: Ids;
let team: number;
let caseBoth: number;
let caseUntranslated: number;
let teamCopy: number;
let pageCopy: number;

test.describe.configure({ mode: 'serial' });
test.setTimeout(300_000);
test.use({ actionTimeout: 30_000 });

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is not set — run the suite via bin/e2e.sh, which seeds and exports it.`);
  }
  return value;
}

async function logIn(page: Page, user: string): Promise<void> {
  // Now and then the click sends no POST at all (nginx's access log shows none); a second try goes through.
  for (let attempt = 1; ; attempt++) {
    await page.goto('/wp/wp-login.php', { waitUntil: 'load' });
    // wp-login's autofocus runs after load and moves focus to the username field; fill() types into whichever field has focus, so wait for it first.
    await expect(page.locator('#user_login')).toBeFocused();
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(requiredEnv('E2E_PASS'));
    await page.locator('#wp-submit').click();
    try {
      await page.waitForURL(/\/wp\/wp-admin\//, { timeout: 40_000, waitUntil: 'commit' });
      return;
    } catch (error) {
      if (attempt === 3) {
        throw error;
      }
    }
  }
}

function json<T>(php: string): T {
  return JSON.parse(ddevWp(php)) as T;
}

/** The newest post of a type in a language, made after this run started. */
function newestCopy(type: string, lang: string): number {
  return Number(ddevWp(`
    global $wpdb;
    $ids = $wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID > %d AND post_type = %s ORDER BY ID DESC", ${start.posts}, '${type}'));
    foreach ($ids as $id) { if (pll_get_post_language((int) $id) === '${lang}') { echo (int) $id; return; } }
    echo 0;`));
}

/** With SHAKEOUT_DIR set (the shake-out run), the viewport as the flow left it. */
async function shot(page: Page, name: string): Promise<void> {
  if (process.env.SHAKEOUT_DIR) {
    await page.screenshot({ path: `${process.env.SHAKEOUT_DIR}/${name}.png` });
  }
}

function post(id: number): { title: string; status: string; content: string; lang: string; unchecked: boolean; meta: Record<string, string>; diensten: string[]; group: Record<string, number> } {
  return json(`
    $p = get_post(${id});
    echo wp_json_encode(['title' => $p->post_title, 'status' => $p->post_status, 'content' => $p->post_content,
      'lang' => pll_get_post_language(${id}), 'unchecked' => get_post_meta(${id}, '_ntdst_translation_unchecked', true) === '1',
      'meta' => array_map(fn ($v) => $v[0], array_filter(get_post_meta(${id}), fn ($k) => str_starts_with($k, '_jw_'), ARRAY_FILTER_USE_KEY)),
      'diensten' => wp_get_object_terms(${id}, 'dienst', ['fields' => 'names']), 'group' => pll_get_post_translations(${id})]);`);
}

async function editorReady(page: Page): Promise<void> {
  await page.waitForFunction(() => (window as any).wp?.data?.select('core/editor')?.getCurrentPostId(), null, { timeout: 90_000 });
}

async function saveDraft(page: Page): Promise<void> {
  await page.getByRole('button', { name: 'Concept opslaan' }).click();
  await page.waitForFunction(() => {
    const wp = (window as any).wp;
    return !wp.data.select('core/editor').isSavingPost()
      && !wp.data.select('core/edit-post').isSavingMetaBoxes()
      && wp.data.select('core/editor').getCurrentPost().status === 'draft';
  }, null, { timeout: 90_000 });
}

/** The block editor's "Meta vakken" drawer, where the ntdst fields live, opened if it is shut. */
async function metaBoxes(page: Page): Promise<void> {
  const phone = page.locator('[name="ntdst_fields[phone]"], [name="ntdst_fields[role]"]').first();
  if (!(await phone.isVisible())) {
    // The drawer's resize handle sits over the middle of its toggle.
    await page.getByRole('button', { name: 'Meta vakken' }).click({ position: { x: 40, y: 10 } });
  }
  await expect(phone).toBeVisible();
}

/** The editor's Diensten panel, opened if it is shut (WordPress remembers it per user). */
async function dienstenPanel(page: Page): Promise<void> {
  const toggle = page.getByRole('button', { name: 'Diensten', exact: true });
  if ((await toggle.getAttribute('aria-expanded')) !== 'true') {
    await toggle.click();
  }
  await expect(page.getByRole('checkbox', { name: FR_DIENST })).toBeVisible();
}

/** Opens the list and clicks Polylang's "+" in a language column. */
async function plus(page: Page, list: string, row: string, lang: string): Promise<void> {
  await page.goto(list);
  await page.locator(`${row} td.column-language_${lang} a.pll_icon_add`).click();
}

/** Quick Edit's status select, the list screen's own way to (un)publish. */
async function quickStatus(page: Page, type: string, id: number, status: 'draft' | 'publish'): Promise<void> {
  await page.goto(`/wp/wp-admin/edit.php?post_type=${type}&lang=all`);
  const row = page.locator(`#post-${id}`);
  await row.locator('.row-title').hover();
  await row.getByRole('button', { name: /snel bewerken/i }).click();
  const editor = page.locator('#the-list tr.inline-editor');
  await editor.locator('select[name="_status"]').selectOption(status);
  await editor.locator('button.save').click();
  await expect(editor).toBeHidden();
}

test.beforeAll(() => {
  const home = new URL(ddevWp('echo home_url();')).origin;
  const base = requiredEnv('WP_BASE_URL');
  if (!home.endsWith('.ddev.site') || home !== new URL(base).origin) {
    throw new Error(`refused: this spec spends the DeepL key and runs on the local DDEV site only, not ${base}`);
  }

  start = json<Ids>(`global $wpdb; echo wp_json_encode(['posts' => (int) $wpdb->get_var("SELECT MAX(ID) FROM $wpdb->posts"), 'terms' => (int) $wpdb->get_var("SELECT MAX(term_id) FROM $wpdb->terms")]);`);

  const made = json<{ team: number; both: number; untranslated: number }>(String.raw`
    $team = wp_insert_post(['post_type' => 'team', 'post_status' => 'publish', 'post_title' => '${TEAM_NAME}'], true);
    pll_set_post_language($team, 'nl');
    foreach (['role' => 'Strategisch adviseur', 'bio' => 'Zoë helpt organisaties hun verhaal helder te vertellen. Ze werkt graag met kleine teams.',
      'email' => 'e2e-zoe@example.test', 'phone' => '${TEAM_PHONE}'] as $k => $v) { update_post_meta($team, "_jw_$k", $v); }

    $case = function (string $title, array $diensten) {
      $id = wp_insert_post(['post_type' => 'case', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '<p>Een korte beschrijving van het project.</p>'], true);
      pll_set_post_language($id, 'nl');
      update_post_meta($id, '_jw_client', '${CASE_CLIENT}');
      wp_set_object_terms($id, $diensten, 'dienst');
      return $id;
    };
    $both = $case('E2E Case met twee diensten', [${DIENST_TRANSLATED}, ${DIENST_UNTRANSLATED}]);
    $untranslated = $case('E2E Case zonder Franse dienst', [${DIENST_UNTRANSLATED}]);

    $fr = wp_insert_term('${FR_DIENST}', 'dienst', ['slug' => 'e2e-strategie-fr']);
    pll_set_term_language($fr['term_id'], 'fr');
    pll_save_term_translations(['nl' => ${DIENST_TRANSLATED}, 'fr' => $fr['term_id']]);

    echo wp_json_encode(['team' => $team, 'both' => $both, 'untranslated' => $untranslated]);`);
  ({ team, both: caseBoth, untranslated: caseUntranslated } = made);
});

test.afterAll(() => {
  if (!start) {
    return;
  }
  // Every post and dienst term this run made, and the Polylang groups they leave behind.
  const left = ddevWp(`
    global $wpdb;
    foreach ($wpdb->get_col($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID > %d AND post_type IN ('page','post','team','case')", ${start.posts})) as $id) { wp_delete_post((int) $id, true); }
    foreach ($wpdb->get_results($wpdb->prepare("SELECT t.term_id, tt.taxonomy FROM $wpdb->terms t JOIN $wpdb->term_taxonomy tt USING (term_id) WHERE t.term_id > %d AND tt.taxonomy IN ('dienst','post_translations','term_translations')", ${start.terms})) as $t) { wp_delete_term((int) $t->term_id, $t->taxonomy); }
    pll_save_term_translations(['nl' => ${DIENST_TRANSLATED}]);
    echo (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->posts WHERE ID > %d AND post_type IN ('page','post','team','case')", ${start.posts}))
      + (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->terms t JOIN $wpdb->term_taxonomy tt USING (term_id) WHERE t.term_id > %d AND tt.taxonomy = 'dienst'", ${start.terms}));`);
  expect(left, 'every post and dienst term the run made is gone').toBe('0');
});

test.describe('translation, local only', () => {
  test('flow 1: "+" on Waarom Jos? opens an English draft that reads Unchecked once saved', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await plus(page, '/wp/wp-admin/edit.php?post_type=page&lang=all', `#post-${PAGE_ID}`, 'en');
    await editorReady(page);

    const opened = await page.evaluate(() => {
      const editor = (window as any).wp.data.select('core/editor');
      return { title: editor.getEditedPostAttribute('title') as string, content: editor.getEditedPostContent() as string };
    });
    expect(opened.title).not.toBe('Waarom Jos?');
    expect(opened.title).toMatch(/Jos\?$/);
    expect(opened.content, 'the YOOtheme layout is in the editor').toMatch(/<!-- \{.*"version":"[\d.]+"\} -->\s*$/s);
    expect(opened.content).not.toContain(PAGE_DUTCH);
    await expect(page.locator('#ntdst_translation_box')).toContainText('I have checked this translation');
    await shot(page, '1-page-plus-editor');

    await saveDraft(page);
    pageCopy = newestCopy('page', 'en');
    const saved = post(pageCopy);
    expect(saved).toMatchObject({ status: 'draft', lang: 'en', unchecked: true, title: opened.title });
    expect(saved.group.nl).toBe(PAGE_ID);
    expect(saved.content, 'the first save keeps the layout').toMatch(/"version":"[\d.]+"\} -->\s*$/);
    expect(saved.content).not.toContain(PAGE_DUTCH);

    await page.goto('/wp/wp-admin/edit.php?post_type=page&lang=en');
    await expect(page.locator(`#post-${pageCopy} td.column-translation`)).toHaveText('Unchecked');
    await page.goto('/wp/wp-admin/edit.php?post_type=page&lang=all&ntdst_translation=unchecked');
    await expect(page.locator('#the-list tr[id^=post-]')).toHaveCount(1);
    await expect(page.locator(`#post-${pageCopy}`)).toBeVisible();
    await expect(page.locator(`#post-${pageCopy} td.column-translation`)).toHaveText('Unchecked');
    await shot(page, '1-page-unchecked');
  });

  test('flow 1, edge 8: the draft\'s preview renders the layout in English', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto(`/?page_id=${pageCopy}&preview=true`);

    await expect(page.locator('html')).toHaveAttribute('lang', 'en-US');
    const hero = page.locator('main h1, h1').first();
    await expect(hero).toBeVisible();
    await expect(hero).not.toContainText('Anders');
    expect((await hero.textContent())!.trim().length).toBeGreaterThan(10);
    await expect(page.locator('body')).not.toContainText(PAGE_DUTCH);
    await shot(page, '1a-preview-en');
  });

  test('flow 1: ticking "I have checked this translation" and saving clears Unchecked', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto(`/wp/wp-admin/post.php?post=${pageCopy}&action=edit`);
    await editorReady(page);
    await page.locator('input[name=ntdst_translation_checked]').check();
    await saveDraft(page);

    expect(post(pageCopy).unchecked).toBe(false);
    await page.goto('/wp/wp-admin/edit.php?post_type=page&lang=en');
    await expect(page.locator(`#post-${pageCopy} td.column-translation`)).toHaveText('');
    await shot(page, '1b-checked-cleared');
  });

  test('flow 1, edge 2: an author who cannot edit the Dutch record gets no translation', async ({ page }) => {
    await logIn(page, 'e2e-author');
    const pageNew = await page.goto(`/wp/wp-admin/post-new.php?post_type=page&from_post=${PAGE_ID}&new_lang=en`);
    expect(pageNew!.status(), 'an author may not make pages at all').toBe(403);

    await plus(page, '/wp/wp-admin/edit.php?post_type=post&lang=all', `#post-${AUTHOR_POST_ID}`, 'en');
    await editorReady(page);
    const title = await page.evaluate(() => (window as any).wp.data.select('core/editor').getEditedPostAttribute('title'));
    expect(title, 'no machine-translated title').toBe('');
    await expect(page.locator('#ntdst_translation_box')).toHaveCount(0);
    await shot(page, '1c-author-no-translation');

    const draft = newestCopy('post', 'en');
    expect(draft, 'the "+" made its auto-draft').toBeGreaterThan(0);
    expect(post(draft), "WordPress's own auto-draft title, nothing filled in").toMatchObject({ title: 'Automatisch concept', unchecked: false });
  });

  test('flow 2, edge 5: "+" on a team member translates role and bio; name and phone stay byte for byte', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await plus(page, '/wp/wp-admin/edit.php?post_type=team&lang=all', `#post-${team}`, 'en');
    await editorReady(page);

    await metaBoxes(page);
    const field = (name: string) => page.locator(`[name="ntdst_fields[${name}]"]`);
    await expect(field('role')).not.toHaveValue('Strategisch adviseur');
    await expect(field('role')).not.toHaveValue('');
    await expect(field('bio')).not.toHaveValue(/helpt organisaties/);
    await expect(field('bio')).toHaveValue(/Zoë/);
    await expect(field('phone')).toHaveValue(TEAM_PHONE);
    expect(await page.evaluate(() => (window as any).wp.data.select('core/editor').getEditedPostAttribute('title'))).toBe(TEAM_NAME);

    await shot(page, '2-team-en');
    await saveDraft(page);
    teamCopy = newestCopy('team', 'en');
    const copy = post(teamCopy);
    expect(Buffer.from(copy.title).equals(Buffer.from(TEAM_NAME)), `name byte for byte, got ${copy.title}`).toBe(true);
    expect(copy).toMatchObject({ lang: 'en', unchecked: true });
    expect(copy.meta._jw_phone).toBe(TEAM_PHONE);
    expect(copy.meta._jw_email).toBe('e2e-zoe@example.test');
    expect(copy.meta._jw_role).not.toBe('Strategisch adviseur');
  });

  test('flow 2: the copy keeps its own phone after the Dutch record is saved', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto(`/wp/wp-admin/post.php?post=${teamCopy}&action=edit`);
    await editorReady(page);
    await metaBoxes(page);
    await page.locator('[name="ntdst_fields[phone]"]').fill(COPY_PHONE);
    await saveDraft(page);
    expect(post(teamCopy).meta._jw_phone).toBe(COPY_PHONE);

    await page.goto(`/wp/wp-admin/post.php?post=${team}&action=edit`);
    await editorReady(page);
    await metaBoxes(page);
    await page.locator('[name="ntdst_fields[role]"]').fill('Strategisch adviseur en coach');
    await page.locator('button.editor-post-publish-button').click();
    await page.waitForFunction(() => {
      const wp = (window as any).wp;
      return !wp.data.select('core/editor').isSavingPost() && !wp.data.select('core/edit-post').isSavingMetaBoxes()
        && !wp.data.select('core/editor').isEditedPostDirty();
    }, null, { timeout: 90_000 });

    expect(post(team).meta._jw_role, 'the Dutch save landed').toBe('Strategisch adviseur en coach');
    expect(post(teamCopy).meta._jw_phone).toBe(COPY_PHONE);
    expect(post(team).meta._jw_phone).toBe(TEAM_PHONE);
    await page.goto(`/wp/wp-admin/post.php?post=${teamCopy}&action=edit`);
    await editorReady(page);
    await metaBoxes(page);
    await expect(page.locator('[name="ntdst_fields[phone]"]')).toHaveValue(COPY_PHONE);
    await shot(page, '2a-copy-keeps-phone');
  });

  test('flow 4: unpublishing the Dutch team member drafts its published English copy; publishing brings it back', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await quickStatus(page, 'team', teamCopy, 'publish');
    expect(post(teamCopy).status).toBe('publish');

    await quickStatus(page, 'team', team, 'draft');
    expect(post(team).status).toBe('draft');
    expect(post(teamCopy).status, 'the copy left publish with the Dutch record').toBe('draft');

    await quickStatus(page, 'team', team, 'publish');
    expect(post(team).status).toBe('publish');
    expect(post(teamCopy).status, 'the copy came back with it').toBe('publish');
    await page.goto('/wp/wp-admin/edit.php?post_type=team&lang=en');
    await expect(page.locator(`#post-${teamCopy} td.column-date`)).toContainText('Gepubliceerd');
    await shot(page, '4-copy-published-again');
  });

  test('flow 3: "+" on a case for French carries the dienst with a French term, no Dutch ones, and the client', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await plus(page, '/wp/wp-admin/edit.php?post_type=case&lang=all', `#post-${caseBoth}`, 'fr');
    await editorReady(page);
    await saveDraft(page);

    const copy = post(newestCopy('case', 'fr'));
    expect(copy).toMatchObject({ lang: 'fr', unchecked: true });
    expect(copy.diensten).toEqual([FR_DIENST]);
    expect(copy.meta._jw_client).toBe(CASE_CLIENT);
    await dienstenPanel(page);
    await expect(page.getByRole('checkbox', { name: FR_DIENST })).toBeChecked();
    await expect(page.getByRole('checkbox', { checked: true }).filter({ hasText: /Strategie|Campagne/ })).toHaveCount(0);
    await shot(page, '3-case-fr');
  });

  test('flow 3, edge 1: a case whose diensten have no French term yet gets none', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await plus(page, '/wp/wp-admin/edit.php?post_type=case&lang=all', `#post-${caseUntranslated}`, 'fr');
    await editorReady(page);
    await saveDraft(page);

    const copy = post(newestCopy('case', 'fr'));
    expect(copy.lang).toBe('fr');
    expect(copy.diensten).toEqual([]);
    expect(copy.meta._jw_client).toBe(CASE_CLIENT);
    await dienstenPanel(page);
    await expect(page.getByRole('checkbox', { name: FR_DIENST })).not.toBeChecked();
    await shot(page, '3a-case-fr-no-dienst');
  });

  test('flow 5: "+" on a Dutch dienst opens the add form in English, linked to it; saving makes the English dienst', async ({ page }) => {
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/edit-tags.php?taxonomy=dienst&post_type=case');
    await page.locator(`#tag-${DIENST_TRANSLATED} td.column-language_en a`).click();

    const name = page.locator('#tag-name');
    await expect(name).not.toHaveValue('');
    await expect(name).not.toHaveValue('Strategie');
    await expect(page.locator('#term_lang_choice option:checked')).toHaveText(/English|EN/i);
    await expect(page.locator('#htr_lang_nl')).toHaveValue(String(DIENST_TRANSLATED));
    const english = await name.inputValue();
    await shot(page, '5-dienst-en-form');

    await page.locator('#submit').click();
    await expect(page.locator('#the-list tr', { hasText: english })).toBeVisible({ timeout: 60_000 });

    const made = json<{ name: string; lang: string; group: Record<string, number> }>(`
      global $wpdb;
      $id = (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(t.term_id) FROM $wpdb->terms t JOIN $wpdb->term_taxonomy tt USING (term_id) WHERE t.term_id > %d AND tt.taxonomy = 'dienst'", ${start.terms}));
      echo wp_json_encode(['name' => get_term($id)->name, 'lang' => pll_get_term_language($id), 'group' => pll_get_term_translations($id)]);`);
    expect(made.name).toBe(english);
    expect(made.lang).toBe('en');
    expect(made.group.nl).toBe(DIENST_TRANSLATED);
  });

  test('flow 5, edge 2: an author who cannot edit the dienst gets no form and no translation', async ({ page }) => {
    await logIn(page, 'e2e-author');
    const response = await page.goto(`/wp/wp-admin/edit-tags.php?taxonomy=dienst&post_type=case&from_tag=${DIENST_UNTRANSLATED}&new_lang=en`);
    expect(response!.status()).toBe(403);
    await expect(page.locator('#tag-name')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('Campaign');
    await shot(page, '5a-author-403');
  });

  test('flows 1 and 5, edge 6: with no key the page draft opens in Dutch, still flagged, and the term form stays empty (wire)', () => {
    // Polylang's "+" replayed through WordPress's own new-post and term-form code, the translator
    // switched off for this one WP-CLI process: no config file or constant is touched.
    const out = json<{ provider: null | string; source: string; page: { title: string; stored: string; flagged: boolean }; term: { source: string; prefill: string } }>(String.raw`
      add_filter('ntdst/baseline/polylang/config', static function (array $config): array {
        $config['translator'] = ['provider' => null, 'key' => ''];
        return $config;
      }, PHP_INT_MAX);
      \ntdst\baseline\services\polylang\PolylangService::reset();
      wp_set_current_user(get_user_by('login', 'e2e-admin')->ID);
      require_once ABSPATH . 'wp-admin/includes/post.php';

      $GLOBALS['pagenow'] = 'post-new.php';
      $_GET = ['post_type' => 'page', 'from_post' => ${PAGE_ID}, 'new_lang' => 'en', '_wpnonce' => wp_create_nonce('new-post-translation')];
      $draft = get_default_post_to_edit('page', true);
      $page = ['title' => apply_filters('default_title', $draft->post_title, $draft), 'stored' => get_post_field('post_title', $draft->ID),
        'flagged' => get_post_meta($draft->ID, '_ntdst_translation_unchecked', true) === '1'];
      wp_delete_post($draft->ID, true);

      $GLOBALS['pagenow'] = 'edit-tags.php';
      $_GET = ['taxonomy' => 'dienst', 'from_tag' => ${DIENST_UNTRANSLATED}, 'new_lang' => 'en'];
      ob_start();
      ntdst_get(\ntdst\baseline\services\polylang\Translation\TermPlusTranslation::class)->prefill();
      $prefill = ob_get_clean();

      echo wp_json_encode(['provider' => \ntdst\baseline\services\polylang\PolylangService::config()['translator']['provider'],
        'source' => get_post_field('post_title', ${PAGE_ID}), 'page' => $page, 'term' => ['source' => get_term(${DIENST_UNTRANSLATED})->name, 'prefill' => $prefill]]);`);

    expect(out.provider).toBeNull();
    expect(out.page).toEqual({ title: out.source, stored: out.source, flagged: true });
    expect(out.term).toEqual({ source: 'Campagne', prefill: '' });
  });
});
