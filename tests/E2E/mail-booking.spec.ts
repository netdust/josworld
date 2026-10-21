import { test, expect, type Page } from '@playwright/test';
import { ddevWp, expectJosLayout, mailbox, openMail, phpString, readLayout, waitForMail, type Mail } from './support/mail';

/**
 * specs/mail-template flow 2: a booking's guest and host mails leave in the JOS layout.
 *
 * LOCAL ONLY. Stefan, 2026-10-21: "no, no test boekings in google calendar" — FluentBooking Pro
 * pushes every new booking to the host's connected Google or Outlook calendar. So this spec
 * carries neither @e2e nor @smoke (make e2e / make smoke never select it), refuses any site but
 * the DDEV one `ddev wp` answers for, refuses while a host of the event has a remote calendar
 * connected, and deletes the booking and the CRM contact it made.
 */

const CONTACT_PAGE = '/contact/';
const SINCE = Date.now();
const TOKEN = `e2e${SINCE}`;
const GUEST_NAME = `Gast ${TOKEN}`;
const GUEST_EMAIL = `e2e-booking-${SINCE}@example.test`;

let booked = false;
let guestMail: Mail;
let hostMail: Mail;
let hostEmail: string;

test.describe.configure({ mode: 'serial' });

/** Throws before anything is booked unless the site is the local DDEV one with no remote calendar. */
async function refuseUnlessLocal(page: Page, baseURL: string | undefined): Promise<void> {
  if (!mailbox(baseURL)) {
    throw new Error(`refused: flow 2 books only on the local DDEV site, not ${baseURL}`);
  }
  const ddevHome = new URL(ddevWp('echo home_url();')).origin;
  if (ddevHome !== new URL(baseURL!).origin) {
    throw new Error(`refused: ddev wp answers for ${ddevHome}, the browser drives ${baseURL}`);
  }

  await page.goto(CONTACT_PAGE);
  const eventId = Number(await page.locator('.fluent_booking_app').first().getAttribute('data-event_id'));
  expect(eventId, 'the booking widget names its event').toBeGreaterThan(0);

  const hosts = JSON.parse(ddevWp(String.raw`
    $event = \FluentBooking\App\Models\CalendarSlot::find(${eventId});
    $hosts = [];
    foreach ($event->getHostIds() as $id) {
      $hosts[] = ['id' => (int) $id, 'email' => get_userdata($id)->user_email,
        'remote' => \FluentBooking\App\Services\Integrations\Calendars\RemoteCalendarHelper::getRemoteCalendarConfig($id)];
    }
    echo wp_json_encode($hosts);
  `)) as { id: number; email: string; remote: unknown }[];

  expect(hosts.length, 'the event has a host').toBeGreaterThan(0);
  for (const host of hosts) {
    expect(host.remote, `refused: host ${host.id} has a remote calendar connected — a booking would reach it`).toBeNull();
  }
  hostEmail = hosts[0].email;
}

async function bookFirstFreeSlot(page: Page): Promise<void> {
  await page.locator('button:has-text("Afwijzen")').first().click({ timeout: 2000 }).catch(() => {});
  const app = page.locator('.fluent_booking_app').first();
  if ((await app.locator('.calendar .day-enabled').count()) === 0) {
    await app.getByRole('button', { name: 'Next Month' }).click();
  }
  await app.locator('.calendar .day-enabled').first().click();
  await app.locator('.fcal_spot_name').first().click();
  await app.locator('.fcal_spot_confirm').click();

  await app.locator('#fcalInputIDname').fill(GUEST_NAME);
  await app.locator('input[type=email]').fill(GUEST_EMAIL);
  await app.locator('textarea').first().fill('Testboeking van de e2e-suite; wordt meteen verwijderd.');
  booked = true;
  await app.locator('.fcal_btn_submit').click();
  await expect(app).toContainText(GUEST_NAME);
}

test.describe('mail layout: a booking (local DDEV only — make e2e and make smoke never select it)', () => {
  test.afterAll(() => {
    if (!booked) {
      return;
    }
    const email = phpString(GUEST_EMAIL);
    const left = ddevWp(String.raw`
      foreach (\FluentBooking\App\Models\Booking::where('email', ${email})->get() as $booking) {
        do_action('fluent_booking/before_delete_booking', $booking);
        $booking->delete();
        do_action('fluent_booking/after_delete_booking', $booking->id);
      }
      $contact = \FluentCrm\App\Models\Subscriber::where('email', ${email})->first();
      if ($contact) { \FluentCrm\App\Services\Helper::deleteContacts([$contact->id]); }
      echo \FluentBooking\App\Models\Booking::where('email', ${email})->count()
        + \FluentCrm\App\Models\Subscriber::where('email', ${email})->count();
    `);
    expect(left, 'the booking and the CRM contact are gone').toBe('0');
  });

  test('flow 2: a booked slot sends the guest a confirmation in the JOS layout', async ({ page, request, baseURL }, testInfo) => {
    test.setTimeout(180_000);
    await refuseUnlessLocal(page, baseURL);
    await bookFirstFreeSlot(page);

    // FluentBooking sends from Action Scheduler, whose queue WP-Cron runs once a minute on a
    // visitor's page load; this plays that visitor until the minute comes round.
    const nextVisit = () => request.get('/wp/wp-cron.php');
    const mails = await waitForMail(request, mailbox(baseURL)!, TOKEN, SINCE, 2, nextVisit, 120_000);
    guestMail = mails.find((mail) => mail.To.some((to) => to.Address === GUEST_EMAIL))!;
    hostMail = mails.find((mail) => mail.To.some((to) => to.Address === hostEmail))!;
    expect(guestMail, 'the guest confirmation').toBeDefined();

    await openMail(page, mailbox(baseURL)!, guestMail);
    await page.screenshot({ path: testInfo.outputPath('booking-guest-mail.png'), fullPage: true });
    await expectJosLayout(page, baseURL!);
    await expect(page.locator('td.jos-message')).toContainText(GUEST_NAME);
  });

  test('flow 2, edge 7: the host notification arrives in the same layout', async ({ page, baseURL }, testInfo) => {
    expect(hostMail, `the host notification to ${hostEmail}`).toBeDefined();

    await openMail(page, mailbox(baseURL)!, hostMail);
    await page.screenshot({ path: testInfo.outputPath('booking-host-mail.png'), fullPage: true });
    await expectJosLayout(page, baseURL!);
    await expect(page.locator('td.jos-message')).toContainText(GUEST_EMAIL);
  });

  test('flow 2, edge 8: brown text on lemon; FluentBooking\'s blue, grey and footer are gone', async ({ page, baseURL }, testInfo) => {
    for (const mail of [guestMail, hostMail]) {
      await openMail(page, mailbox(baseURL)!, mail);
      const look = await page.evaluate(() => {
        const card = document.querySelector<HTMLElement>('td.jos-message')!;
        const all = Array.from(document.querySelectorAll<HTMLElement>('body *'));
        const blue = 'rgb(0, 105, 255)';
        const outside = document.body.cloneNode(true) as HTMLElement;
        outside.querySelector('td.jos-message')!.remove();
        return {
          blue: all.filter((el) => {
            const cs = getComputedStyle(el);
            const blueBorder = cs.borderTopWidth !== '0px' && cs.borderTopColor === blue;
            return cs.color === blue || cs.backgroundColor === blue || blueBorder;
          }).map((el) => el.tagName),
          grey: Array.from(card.querySelectorAll<HTMLElement>('*'))
            .filter((el) => el.innerText?.trim() && getComputedStyle(el).color === 'rgb(68, 68, 68)')
            .map((el) => el.innerText.trim().slice(0, 40)),
          paragraphColours: [...new Set(Array.from(card.querySelectorAll('p')).map((p) => getComputedStyle(p).color))],
          textOutsideCard: outside.innerText.replace(/\s+/g, ' ').trim(),
        };
      });
      expect(look.blue, `${mail.Subject}: nothing in FluentBooking's blue`).toEqual([]);
      expect(look.grey, `${mail.Subject}: no text left in FluentBooking's grey`).toEqual([]);
      expect(look.paragraphColours, `${mail.Subject}: every paragraph brown`).toEqual(['rgb(53, 7, 12)']);
      expect(look.textOutsideCard, `${mail.Subject}: no FluentBooking footer, only the JOS one`).toBe('JOS · jos.agency');
    }
    await page.locator('td.jos-message').screenshot({ path: testInfo.outputPath('booking-card.png') });
  });

  test('flow 2: the reschedule and cancel links in the guest mail open FluentBooking\'s page', async ({ page, baseURL }, testInfo) => {
    for (const type of ['reschedule', 'cancel']) {
      await openMail(page, mailbox(baseURL)!, guestMail);
      await page.locator(`td.jos-message a[href*="type=${type}"]`).click();
      await expect(page).toHaveURL(new RegExp(`type=${type}`));
      await expect(page.locator('body')).toContainText('Sparmoment');
      await page.screenshot({ path: testInfo.outputPath(`booking-${type}-page.png`) });
    }
  });

  test('flow 2, edge 5: at 375px both mails stay inside the screen, the guest\'s long address included', async ({ page, baseURL }, testInfo) => {
    await page.setViewportSize({ width: 375, height: 812 });
    for (const [name, mail] of [['guest', guestMail], ['host', hostMail]] as const) {
      await openMail(page, mailbox(baseURL)!, mail);
      await page.screenshot({ path: testInfo.outputPath(`booking-${name}-375.png`), fullPage: true });

      const { card, pageWidth } = await readLayout(page);
      expect(pageWidth, `${name} mail: nothing scrolls sideways`).toBeLessThanOrEqual(375);
      expect(card!.left).toBeGreaterThanOrEqual(0);
      expect(card!.right).toBeLessThanOrEqual(375);
    }
  });
});
