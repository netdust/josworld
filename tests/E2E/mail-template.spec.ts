import { test, expect, type APIRequestContext, type Page } from '@playwright/test';
import { ddevWp, expectJosLayout, mailbox, openMail, phpString, readLayout, waitForMail, type Mail } from './support/mail';

/**
 * specs/mail-template flows 1 and 3: every mail the site sends leaves in the JOS layout.
 *
 * Both run on staging under @e2e, where outgoing mail is blocked: there they prove the send
 * went through (the form's thank-you, WordPress's "check your email" screen). The mail itself
 * is read back only locally, in Mailpit. Flow 2 (booking) is mail-booking.spec.ts, local only.
 */

const TOOL_PAGE = '/tools/canvas-gedrag/';
const LEAD_EMAIL = 'e2e-tool-download@example.test';
const RESET_LOGIN = 'e2e-author';

const mailOnlyLocally = 'outgoing mail is blocked here; only DDEV catches it';

async function downloadTool(page: Page, name: string): Promise<void> {
  await page.goto(TOOL_PAGE);
  await page.locator('button:has-text("Afwijzen")').first().click({ timeout: 2000 }).catch(() => {});
  const form = page.locator('form.fluent_form_3');
  await form.locator('[name=naam]').fill(name);
  await form.locator('[name=organisatie]').fill('E2E Organisatie');
  await form.locator('[name=email]').fill(LEAD_EMAIL);
  await form.locator('[name=privacy]').check({ force: true });
  await form.locator('button[type=submit]').click();
  await expect(page.locator('.ff-message-success')).toContainText('Bedankt!');
}

/** The reset mail asked at or after `since`, read back in Mailpit. */
async function resetMail(request: APIRequestContext, box: string, since: number): Promise<Mail> {
  const to = ddevWp(`echo get_user_by('login', ${phpString(RESET_LOGIN)})->user_email;`);
  const [mail] = await waitForMail(request, box, `to:"${to}" subject:"Wachtwoord opnieuw instellen"`, since);
  return mail;
}

async function requestReset(page: Page, login: string): Promise<void> {
  await page.goto('/wp/wp-login.php?action=lostpassword');
  await page.locator('#user_login').fill(login);
  await page.locator('#wp-submit').click();
  await expect(page.locator('#login_error'), `${login} must exist on this environment`).toHaveCount(0);
  await expect(page).toHaveURL(/checkemail=confirm/);
}

test.describe('mail layout: the tool download notification @e2e', () => {
  test.afterAll(({ baseURL }) => {
    if (!mailbox(baseURL)) {
      return;
    }
    const email = phpString(LEAD_EMAIL);
    ddevWp(String.raw`
      $contact = \FluentCrm\App\Models\Subscriber::where('email', ${email})->first();
      if ($contact) { \FluentCrm\App\Services\Helper::deleteContacts([$contact->id]); }
      $ids = \FluentForm\App\Models\Submission::where('form_id', 3)->where('response', 'like', '%' . ${email} . '%')->pluck('id')->toArray();
      if ($ids) { (new \FluentForm\App\Services\Submission\SubmissionService())->deleteEntries($ids, 3); }
    `);
  });

  test('flow 1: the download reaches the admin in the JOS layout, the submission table inside the card', async ({ page, request, baseURL }, testInfo) => {
    const since = Date.now();
    const token = `e2e${since}`;
    await downloadTool(page, `Sarah ${token}`);

    const box = mailbox(baseURL);
    if (!box) {
      testInfo.annotations.push({ type: 'mail not read', description: mailOnlyLocally });
      return;
    }

    const [mail] = await waitForMail(request, box, token, since);
    expect(mail.To.map((to) => to.Address)).toEqual([ddevWp("echo get_option('admin_email');")]);
    expect(mail.Subject).toMatch(/^Tool gedownload: /);

    await openMail(page, box, mail);
    await expectJosLayout(page, baseURL!);

    const table = page.locator('td.jos-message table.ff_all_data');
    await expect(table).toContainText(`Sarah ${token}`);
    await expect(table).toContainText(LEAD_EMAIL);
    const { card } = await readLayout(page);
    const tableBox = (await table.boundingBox())!;
    expect(Math.round(tableBox.x), 'the table starts inside the card').toBeGreaterThanOrEqual(card!.left);
    expect(Math.round(tableBox.x + tableBox.width), 'the table ends inside the card').toBeLessThanOrEqual(card!.right);

    await page.screenshot({ path: testInfo.outputPath('tool-download-mail.png'), fullPage: true });
  });

  test('flow 1, edge 5: a name with <b> and an emoji reads as text', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, mailOnlyLocally);

    const since = Date.now();
    const token = `e2e${since}`;
    await downloadTool(page, `<b>Jos</b> 🍋 ${token}`);

    const [mail] = await waitForMail(request, box!, token, since);
    await openMail(page, box!, mail);

    const name = page.locator('table.ff_all_data td', { hasText: token });
    await expect(name).toHaveCount(1);
    await expect(name.locator('b, strong'), 'the <b> the visitor typed is not markup').toHaveCount(0);
    // WordPress turns an emoji in an HTML mail into its emoji image (wp_staticize_emoji_for_email);
    // a reader sees the image, or its alt text when images are off.
    const read = await name.evaluate((cell) => {
      const copy = cell.cloneNode(true) as HTMLElement;
      copy.querySelectorAll('img').forEach((img) => img.replaceWith(img.alt));
      return copy.textContent ?? '';
    });
    expect(read).toMatch(new RegExp(`Jos(</b>)? 🍋 ${token}`));

    await name.screenshot({ path: testInfo.outputPath('markup-emoji.png') });
    await page.screenshot({ path: testInfo.outputPath('markup-emoji-mail.png'), fullPage: true });
  });

  test('flow 1, edge 5: at 375px the card stays inside the screen', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, mailOnlyLocally);

    const since = Date.now();
    const token = `e2e${since}`;
    await downloadTool(page, `Sarah ${token}`);

    const [mail] = await waitForMail(request, box!, token, since);
    await page.setViewportSize({ width: 375, height: 812 });
    await openMail(page, box!, mail);
    await page.screenshot({ path: testInfo.outputPath('tool-download-375.png'), fullPage: true });

    const { card, pageWidth } = await readLayout(page);
    expect(pageWidth, 'nothing scrolls sideways').toBeLessThanOrEqual(375);
    expect(card!.left).toBeGreaterThanOrEqual(0);
    expect(card!.right).toBeLessThanOrEqual(375);
    const table = (await page.locator('table.ff_all_data').boundingBox())!;
    expect(Math.round(table.x + table.width), 'the table ends inside the card').toBeLessThanOrEqual(card!.right);
  });
});

test.describe('mail layout: the lost-password mail @e2e', () => {
  test('flow 3: the reset mail arrives in the JOS layout as readable text with a clickable link', async ({ page, request, baseURL }, testInfo) => {
    const since = Date.now();
    await requestReset(page, RESET_LOGIN);

    const box = mailbox(baseURL);
    if (!box) {
      testInfo.annotations.push({ type: 'mail not read', description: mailOnlyLocally });
      return;
    }

    await openMail(page, box, await resetMail(request, box, since));
    await expectJosLayout(page, baseURL!);

    const message = page.locator('td.jos-message');
    expect(await message.locator('p').count(), 'paragraphs, not one run-on line').toBeGreaterThanOrEqual(3);
    await expect(message).toContainText(RESET_LOGIN);
    await expect(message).not.toContainText('<p>');
    await expect(message).not.toContainText('&amp;');
    const link = message.locator('a[href*="action=rp"]');
    await expect(link).toHaveCount(1);
    await expect(link).toContainText('action=rp');

    await page.screenshot({ path: testInfo.outputPath('reset-mail.png'), fullPage: true, mask: [link] });
  });

  test('flow 3, edge 8: the reset link, clicked, opens WordPress\'s new-password form', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, mailOnlyLocally);

    const since = Date.now();
    await requestReset(page, RESET_LOGIN);
    const mail = await resetMail(request, box!, since);
    await openMail(page, box!, mail);

    await page.locator('td.jos-message a[href*="action=rp"]').click();
    await expect(page.locator('#resetpassform')).toBeVisible();
    await expect(page.locator('#pass1')).toBeVisible();
    await expect(page.locator('#login_error')).toHaveCount(0);

    await page.screenshot({ path: testInfo.outputPath('new-password-form.png'), mask: [page.locator('#pass1')] });
  });

  test('flow 3, hunt: at 375px the reset mail stays inside the screen', async ({ page, request, baseURL }, testInfo) => {
    const box = mailbox(baseURL);
    test.skip(!box, mailOnlyLocally);

    const since = Date.now();
    await requestReset(page, RESET_LOGIN);
    const mail = await resetMail(request, box!, since);
    await page.setViewportSize({ width: 375, height: 812 });
    await openMail(page, box!, mail);
    await page.screenshot({ path: testInfo.outputPath('reset-mail-375.png'), mask: [page.locator('a[href*="action=rp"]')] });

    const { card, pageWidth } = await readLayout(page);
    expect(pageWidth, 'nothing scrolls sideways').toBeLessThanOrEqual(375);
    expect(card!.right).toBeLessThanOrEqual(375);
  });

  test('flow 3, hunt: a mail sent outside a web request (WP-CLI, a system cron) takes its logo from the site\'s own https address', async ({ page, request, baseURL }) => {
    const box = mailbox(baseURL);
    test.skip(!box, mailOnlyLocally);

    const since = Date.now();
    ddevWp(`retrieve_password(${phpString(RESET_LOGIN)});`);
    const mail = await resetMail(request, box!, since);
    await openMail(page, box!, mail);

    const logo = await page.locator('img[alt="JOS"]').getAttribute('src');
    expect(new URL(logo!).origin).toBe(new URL(baseURL!).origin);
  });
});
