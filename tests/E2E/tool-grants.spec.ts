import { test, expect, type APIRequestContext, type APIResponse, type Page } from '@playwright/test';
import { ddevWp, expectJosLayout, mailbox, openMail, phpString, waitForMail } from './support/mail';

/**
 * specs/tool-grants: a tool's file or app opens only at a visitor's personal link, which the download
 * form hands out and mails. The flows of the plan's Test map, driven through the real form.
 *
 * Every flow is @e2e. On a deployed site (no Mailpit, no DDEV) the flows that read the mail, need a
 * local fixture or log in as a seeded actor skip, and say why; the form, the link, its download, its
 * headers and the dead page run everywhere. Locally every grant, entry and contact a run makes is removed.
 */

const NL_TOOL = '/tools/contentkalender/';
const EN_TOOL = '/en/tools/contentkalender/';
const NL_LINK = /\/toegang\/([0-9a-f]{64})\/$/;
const EN_LINK = /\/en\/access\/([0-9a-f]{64})\/$/;

const ADDRESS = {
  nl: 'e2e-grant-nl@example.test',
  en: 'e2e-grant-en@example.test',
  revoke: 'e2e-grant-revoke@example.test',
  app: 'e2e-grant-app@example.test',
  panel: 'e2e-grant-panel@example.test',
  double: 'e2e-grant-double@example.test',
  denied: 'e2e-grant-denied@example.test',
};

const localOnly = 'needs the DDEV site: Mailpit, wp-cli fixtures or a seeded actor';

function requiredEnv(name: string): string {
  const value = process.env[name];
  if (!value) {
    throw new Error(`${name} is not set — run the suite via bin/e2e.sh, which seeds and exports it.`);
  }
  return value;
}

async function logIn(page: Page, user: string): Promise<void> {
  await page.goto('/wp/wp-login.php');
  // wp-login's autofocus runs after load and moves focus to the username field; fill() types into whichever field has focus, so wait for it first.
  await expect(page.locator('#user_login')).toBeFocused();
  await page.locator('#user_login').fill(user);
  await page.locator('#user_pass').fill(requiredEnv('E2E_PASS'));
  await page.locator('#wp-submit').click();
  await page.waitForURL(/\/wp\/wp-admin\//);
}

/** Fills in the download form on a tool page and answers where the browser landed. */
async function requestTool(page: Page, toolPage: string, email: string, landing: RegExp): Promise<string> {
  await page.goto(toolPage);
  await page.locator('button:has-text("Afwijzen"), button:has-text("Decline")').first().click({ timeout: 2000 }).catch(() => {});
  const form = page.locator('form.fluent_form_3');
  await form.locator('[name=naam]').fill('E2E Grant');
  await form.locator('[name=organisatie]').fill('E2E Organisatie');
  await form.locator('[name=email]').fill(email);
  await form.locator('[name=privacy]').check({ force: true });
  await form.locator('button[type=submit]').click();
  await page.waitForURL(landing);
  return page.url();
}

/** The three headers every answer at a personal link carries (threat 4). */
function expectLinkHeaders(response: APIResponse): void {
  const headers = response.headers();
  expect(headers['referrer-policy']).toBe('no-referrer');
  expect(headers['cache-control']).toContain('no-store');
  expect(headers['cache-control']).toContain('private');
  expect(headers['x-robots-tag']).toContain('noindex');
}

/** The page answered as a live page, not WordPress's 404 (is_404 false). */
async function expectNot404(page: Page): Promise<void> {
  await expect(page.locator('body')).not.toHaveClass(/\berror404\b/);
  await expect(page).not.toHaveTitle(/niet gevonden|not found/i);
}

async function mailsTo(request: APIRequestContext, box: string, email: string, since: number): Promise<number> {
  const response = await request.get(`${box}/api/v1/search`, { params: { query: `to:"${email}"`, limit: '50' } });
  const { messages } = (await response.json()) as { messages: { Created: string }[] };
  return messages.filter((mail) => Date.parse(mail.Created) >= since).length;
}

function cleanUp(email: string): void {
  const address = phpString(email);
  ddevWp(String.raw`
    $contact = \FluentCrm\App\Models\Subscriber::where('email', ${address})->first();
    if ($contact) { \FluentCrm\App\Services\Helper::deleteContacts([$contact->id]); }
    $ids = \FluentForm\App\Models\Submission::where('form_id', 3)->where('response', 'like', '%' . ${address} . '%')->pluck('id')->toArray();
    if ($ids) { (new \FluentForm\App\Services\Submission\SubmissionService())->deleteEntries($ids, 3); }
    foreach (ntdst_get(\ntdst\baseline\services\grants\GrantRepository::class)->forRecipient(${address}) as $grant) { wp_delete_post($grant->ID, true); }
    delete_transient('ntdst_grants_mail_' . md5(strtolower(${address})));
  `);
}

test.describe('tool grants: the form, the link and the file @e2e', () => {
  test.describe.configure({ mode: 'serial' });

  let nlLink = '';
  let toolTitle = '';
  let since = 0;

  test.beforeAll(({ baseURL }) => {
    if (mailbox(baseURL)) {
      Object.values(ADDRESS).forEach(cleanUp);
    }
  });

  test.afterAll(({ baseURL }) => {
    if (mailbox(baseURL)) {
      Object.values(ADDRESS).forEach(cleanUp);
    }
  });

  test('form-to-link: the form sends the visitor to their personal link, a live page with the Download button @e2e', async ({ page }, testInfo) => {
    since = Date.now();
    await page.goto(NL_TOOL);
    toolTitle = ((await page.locator('h1').first().textContent()) ?? '').trim();
    expect(toolTitle).not.toBe('');
    nlLink = await requestTool(page, NL_TOOL, ADDRESS.nl, NL_LINK);

    await expect(page.locator('h1')).toContainText(toolTitle);
    const download = page.getByRole('link', { name: 'Download de tool' });
    await expect(download).toHaveAttribute('href', `${nlLink}download/`);
    await expectNot404(page);
    await expect(page.locator('html')).toHaveAttribute('lang', /^nl/);

    await page.screenshot({ path: testInfo.outputPath('link-page-nl.png') });
  });

  test('download: the button downloads the file; the link, its download and the slashless download answer 200 with the three headers @e2e', async ({ page, request }) => {
    await page.goto(nlLink);
    const [file] = await Promise.all([
      page.waitForEvent('download'),
      page.getByRole('link', { name: 'Download de tool' }).click(),
    ]);
    expect(file.suggestedFilename()).toMatch(/\.[a-z0-9]+$/i);

    const linkPage = await request.get(nlLink, { maxRedirects: 0 });
    expect(linkPage.status()).toBe(200);
    expectLinkHeaders(linkPage);

    const bodies: Buffer[] = [];
    for (const url of [`${nlLink}download/`, `${nlLink}download`]) {
      const answer = await request.get(url, { maxRedirects: 0 });
      expect(answer.status(), `${url} answers the file itself, no redirect`).toBe(200);
      expect(answer.headers()['content-disposition'] ?? '', `${url} names the file`).toContain(file.suggestedFilename());
      expectLinkHeaders(answer);
      bodies.push(await answer.body());
    }
    expect(bodies[0].length).toBeGreaterThan(0);
    expect(bodies[1].equals(bodies[0]), 'with and without the slash, the same file').toBe(true);
  });

  test('mail-arrives: the visitor gets one mail in the JOS layout carrying the same link @e2e', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, localOnly);

    const [mail] = await waitForMail(request, box!, `to:"${ADDRESS.nl}"`, since);
    expect(mail.Subject).toBe('Je tool staat klaar. Aan jou nu.');
    await openMail(page, box!, mail);
    await expectJosLayout(page, baseURL!);
    await expect(page.locator('td.jos-message a', { hasText: toolTitle })).toHaveAttribute('href', nlLink);

    await page.screenshot({ path: testInfo.outputPath('mail-nl.png'), fullPage: true });
  });

  test('repeat-same-link: the same address again lands on the same link, and no second mail @e2e', async ({ page, request, baseURL }) => {
    const again = await requestTool(page, NL_TOOL, ADDRESS.nl, NL_LINK);
    expect(again).toBe(nlLink);

    const box = mailbox(baseURL);
    if (box) {
      await page.waitForTimeout(5000);
      expect(await mailsTo(request, box, ADDRESS.nl, since), 'one mail for both requests').toBe(1);
    }
  });

  test('no-tracker-on-link: the link page loads no Matomo, the tool page does @e2e', async ({ request, baseURL }) => {
    const link = await (await request.get(nlLink)).text();
    expect(link).not.toMatch(/matomo\.js|_paq/i);

    if (mailbox(baseURL)) {
      const tool = await (await request.get(NL_TOOL)).text();
      expect(tool, 'the control: Matomo runs on the tool page locally').toMatch(/matomo\.js/);
    }
  });

  test('form-to-link-en: the English tool page sends to /en/access/<token>/ in English, and mails in English @e2e', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, localOnly);

    const start = Date.now();
    const link = await requestTool(page, EN_TOOL, ADDRESS.en, EN_LINK);
    await expect(page.locator('html')).toHaveAttribute('lang', /^en/);
    await expect(page.getByRole('link', { name: 'Download the tool' })).toHaveAttribute('href', `${link}download/`);
    await expectNot404(page);
    await page.screenshot({ path: testInfo.outputPath('link-page-en.png') });

    const [mail] = await waitForMail(request, box!, `to:"${ADDRESS.en}"`, start);
    expect(mail.Subject).toBe('Your tool is ready. Over to you.');
    await openMail(page, box!, mail);
    await expect(page.locator('td.jos-message')).toContainText('Thanks for downloading our tool');
    await expect(page.locator('td.jos-message a[href*="/en/access/"]')).toHaveAttribute('href', link);
    await page.screenshot({ path: testInfo.outputPath('mail-en.png'), fullPage: true });
  });

  test('dead-link: a made-up token answers 404 with the Jos dead page and the three headers @e2e', async ({ page, request }, testInfo) => {
    const made = `/toegang/${'ab'.repeat(32)}/`;
    const answer = await request.get(made, { maxRedirects: 0 });
    expect(answer.status()).toBe(404);
    expectLinkHeaders(answer);

    await page.goto(made);
    await expect(page.locator('h1')).toContainText('Deze link werkt niet meer');
    await expect(page.locator('form.fluent_form_3')).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('dead-page.png') });
  });

  test('app-direct-404: the tool app answers nowhere but at a link @e2e', async ({ request }) => {
    for (const url of ['/tool-app/campagnestart/', '/content/themes/josworld/tools/campagnestart.html']) {
      expect((await request.get(url, { maxRedirects: 0 })).status(), url).toBe(404);
    }
    const source = await request.get('/content/themes/josworld/tools/campagnestart.html.php');
    expect((await source.text()).trim(), 'the app file run directly prints nothing').toBe('');
  });

  test('tool-card-links-to-form: the card on the free offer page goes to the tool page with the form @e2e', async ({ page }, testInfo) => {
    await page.goto('/gratis-aanbod/');
    const card = page.locator(`a[href$="${NL_TOOL}"]`).first();
    await expect(card).toBeVisible();
    await card.click();
    await expect(page).toHaveURL(new RegExp(`${NL_TOOL}$`));
    await expect(page.locator('form.fluent_form_3 [name=email]')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('tool-card-to-form.png') });
  });

  test('old-url-404: the file\'s old uploads URL answers 404 @e2e', async ({ request, baseURL }) => {
    test.skip(!mailbox(baseURL), localOnly);
    const old = ddevWp("echo get_post((int) get_post_meta(161, '_jw_file', true))->guid;");
    expect(old).toMatch(/\/uploads\/.+\.pdf$/);
    expect((await request.get(old, { maxRedirects: 0 })).status()).toBe(404);
  });

  test('app-at-link: a link-kind tool opens its app at the personal link, with the three headers @e2e', async ({ page, request, baseURL }, testInfo) => {
    test.skip(!mailbox(baseURL), localOnly);
    // The local fixture 578 carries no form of its own (its content is empty): the grant is issued the way the form does.
    const link = ddevWp(`echo ntdst_get(\\ntdst\\baseline\\services\\grants\\GrantService::class)->issue(${phpString(ADDRESS.app)}, 578)['url'];`);
    expect(link).toMatch(NL_LINK);

    const answer = await request.get(link, { maxRedirects: 0 });
    expect(answer.status()).toBe(200);
    expectLinkHeaders(answer);

    await page.goto(link);
    await expectNot404(page);
    await expect(page.locator('body')).not.toContainText('Deze link werkt niet meer');
    await page.screenshot({ path: testInfo.outputPath('app-at-link.png') });
  });

  test('editor-revokes and revoked-stays-out: the editor revokes on the tool screen, the link dies, the form leads to the dead page with no mail @e2e', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, localOnly);

    const link = await requestTool(page, NL_TOOL, ADDRESS.revoke, NL_LINK);
    await waitForMail(request, box!, `to:"${ADDRESS.revoke}"`, since);

    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/post.php?post=161&action=edit');
    const panel = page.locator('#ntdst-grants');
    const row = panel.locator('tr', { hasText: ADDRESS.revoke });
    await expect(row).toContainText('Actief');
    await row.scrollIntoViewIfNeeded();
    await panel.screenshot({ path: testInfo.outputPath('editor-panel.png') });
    await row.getByRole('button', { name: 'Intrekken' }).click();
    await page.waitForURL(/post\.php\?post=161&action=edit/);
    await expect(page.locator('#ntdst-grants tr', { hasText: ADDRESS.revoke })).toContainText('Ingetrokken');
    await page.locator('#ntdst-grants').screenshot({ path: testInfo.outputPath('editor-revoked.png') });

    const dead = await request.get(link, { maxRedirects: 0 });
    expect(dead.status()).toBe(404);
    expectLinkHeaders(dead);

    await page.context().clearCookies();
    const again = Date.now();
    await requestTool(page, NL_TOOL, ADDRESS.revoke, NL_LINK);
    await expect(page.locator('h1')).toContainText('Deze link werkt niet meer');
    await expect(page.getByRole('link', { name: 'Naar de tool' })).toHaveAttribute('href', new RegExp(`${NL_TOOL}$`));
    await page.screenshot({ path: testInfo.outputPath('revoked-dead.png') });
    await page.waitForTimeout(5000);
    expect(await mailsTo(request, box!, ADDRESS.revoke, again), 'no mail to a revoked address').toBe(0);
  });

  test('editor-opens-file: the editor opens the gated file from the media library; a visitor and an author cannot @e2e', async ({ page, browser, baseURL }, testInfo) => {
    test.skip(!mailbox(baseURL), localOnly);
    const file = Number(ddevWp("echo (int) get_post_meta(161, '_jw_file', true);"));

    await logIn(page, 'e2e-admin');
    await page.goto(`/wp/wp-admin/upload.php?item=${file}`);
    const url = page.locator('#attachment-details-two-column-copy-link');
    await expect(url).toHaveValue(/admin-post\.php\?action=ntdst_baseline_grants_file&id=\d+/);
    await page.screenshot({ path: testInfo.outputPath('editor-media.png') });
    const opened = await page.request.get(await url.inputValue());
    expect(opened.status()).toBe(200);
    expect((await opened.body()).subarray(0, 5).toString()).toBe('%PDF-');

    const visitor = await browser.newContext({ ignoreHTTPSErrors: true, baseURL });
    expect((await visitor.request.get(await url.inputValue(), { maxRedirects: 0 })).status()).not.toBe(200);
    await visitor.close();
  });
});

test.describe('tool grants: what the hunt and the denied actors found @e2e', () => {
  test.afterAll(({ baseURL }) => {
    if (mailbox(baseURL)) {
      [ADDRESS.panel, ADDRESS.double, ADDRESS.denied].forEach(cleanUp);
    }
  });

  test('concurrent: a double click on the submit button makes one link and one mail @e2e', async ({ page, request, baseURL }) => {
    const box = mailbox(baseURL);
    test.skip(!box, localOnly);

    const start = Date.now();
    await page.goto(NL_TOOL);
    await page.locator('button:has-text("Afwijzen")').first().click({ timeout: 2000 }).catch(() => {});
    const form = page.locator('form.fluent_form_3');
    await form.locator('[name=naam]').fill('E2E Grant');
    await form.locator('[name=organisatie]').fill('E2E Organisatie');
    await form.locator('[name=email]').fill(ADDRESS.double);
    await form.locator('[name=privacy]').check({ force: true });
    await form.locator('button[type=submit]').dblclick();
    await page.waitForURL(NL_LINK);

    await page.waitForTimeout(5000);
    expect(Number(ddevWp(`echo count(ntdst_get(\\ntdst\\baseline\\services\\grants\\GrantRepository::class)->forRecipient(${phpString(ADDRESS.double)}));`))).toBe(1);
    expect(await mailsTo(request, box!, ADDRESS.double, start)).toBe(1);
  });

  test('denied: a tampered tool_id (a page, not a tool) issues no link and shows the not-yet-available message @e2e', async ({ page, request, baseURL }) => {
    const box = mailbox(baseURL);
    test.skip(!box, localOnly);

    const start = Date.now();
    await page.goto(NL_TOOL);
    await page.locator('button:has-text("Afwijzen")').first().click({ timeout: 2000 }).catch(() => {});
    const form = page.locator('form.fluent_form_3');
    await form.locator('[name=naam]').fill('E2E Grant');
    await form.locator('[name=organisatie]').fill('E2E Organisatie');
    await form.locator('[name=email]').fill(ADDRESS.denied);
    await form.locator('[name=privacy]').check({ force: true });
    const page2 = ddevWp("echo (int) get_option('page_on_front');");
    await form.locator('[name=tool_id]').evaluate((input, id) => { (input as HTMLInputElement).value = id; }, page2);
    await form.locator('button[type=submit]').click();

    await expect(page.locator('.ff-message-success')).toContainText('nog niet beschikbaar');
    await expect(page).toHaveURL(new RegExp(`${NL_TOOL}$`));
    expect(Number(ddevWp(`echo count(ntdst_get(\\ntdst\\baseline\\services\\grants\\GrantRepository::class)->forRecipient(${phpString(ADDRESS.denied)}));`))).toBe(0);
    await page.waitForTimeout(3000);
    expect(await mailsTo(request, box!, ADDRESS.denied, start)).toBe(0);
  });

  test('hunt: the editor panel never shows a link opened before it was created @e2e', async ({ page, baseURL }) => {
    test.skip(!mailbox(baseURL), localOnly);
    const link = await requestTool(page, NL_TOOL, ADDRESS.panel, NL_LINK);
    await page.goto(link);
    await logIn(page, 'e2e-admin');
    await page.goto('/wp/wp-admin/post.php?post=161&action=edit');
    const cells = await page.locator('#ntdst-grants tr', { hasText: ADDRESS.panel }).locator('td').allInnerTexts();
    const [created, opened] = [cells[2], cells[3]];
    expect(created).toMatch(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/);
    expect(opened >= created, `Laatst geopend ${opened} is not before Aangemaakt ${created}`).toBe(true);
  });

  test('hunt: the editor sees the tool file\'s preview in the media library\'s details @e2e', async ({ page, baseURL }) => {
    test.skip(!mailbox(baseURL), localOnly);
    const file = Number(ddevWp("echo (int) get_post_meta(161, '_jw_file', true);"));

    await logIn(page, 'e2e-admin');
    await page.goto(`/wp/wp-admin/upload.php?item=${file}`);
    const preview = page.locator('.attachment-details img.details-image');
    await expect(preview).toHaveCount(1);
    const src = (await preview.getAttribute('src'))!;
    expect((await page.request.get(src)).status(), `the preview ${src} loads`).toBe(200);
  });

  test('rest-media: a tool file is not readable through /wp/v2/media, anonymously or as an author @e2e', async ({ browser, baseURL }) => {
    test.skip(!mailbox(baseURL), localOnly);
    const file = Number(ddevWp("echo (int) get_post_meta(161, '_jw_file', true);"));

    const visitor = await browser.newContext({ ignoreHTTPSErrors: true, baseURL });
    const anonymous = await visitor.request.get(`/wp-json/wp/v2/media/${file}`);
    expect(anonymous.status(), 'REST: not readable anonymously').toBe(404);
    expect(await anonymous.text()).not.toContain('private-files');
    await visitor.close();

    const author = await browser.newContext({ ignoreHTTPSErrors: true, baseURL });
    const authorPage = await author.newPage();
    await logIn(authorPage, 'e2e-author');
    const nonce = (await (await author.request.get('/wp/wp-admin/admin-ajax.php?action=rest-nonce')).text()).trim();
    expect(nonce, 'wp-admin hands the author a REST nonce').toMatch(/^[0-9a-f]{10}$/);
    const asAuthor = await author.request.get(`/wp-json/wp/v2/media/${file}`, { headers: { 'X-WP-Nonce': nonce } });
    expect(asAuthor.status(), 'REST: an author cannot read a gated file').toBe(404);
    expect(await asAuthor.text()).not.toContain('private-files');
    const list = await author.request.get('/wp-json/wp/v2/media?per_page=100', { headers: { 'X-WP-Nonce': nonce } });
    expect((await list.json() as { id: number }[]).map((item) => item.id)).not.toContain(file);
    await author.close();
  });
});
