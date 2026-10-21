import { execFileSync } from 'node:child_process';
import { expect, type APIRequestContext, type Page } from '@playwright/test';

/**
 * Reading back the mail a flow sent (specs/mail-template).
 *
 * The local DDEV site catches every mail in Mailpit. A deployed environment blocks outgoing
 * mail (`scripts/remote/00-block-outgoing-mail.php`), so there `mailbox()` answers null and a
 * flow proves only that the send went through.
 */

export interface Mail {
  ID: string;
  Subject: string;
  Created: string;
  To: { Address: string }[];
}

export function mailbox(baseURL: string | undefined): string | null {
  const host = new URL(baseURL ?? 'http://invalid').hostname;
  return host.endsWith('.ddev.site') ? `https://${host}:8026` : null;
}

/**
 * The mails matching a Mailpit search that arrived after `since`, waited for until `count` are in.
 * `nudge` runs before every look, for a sender that only sends when something else happens.
 */
export async function waitForMail(
  request: APIRequestContext,
  box: string,
  query: string,
  since: number,
  count = 1,
  nudge?: () => Promise<unknown>,
  timeout = 30_000,
): Promise<Mail[]> {
  let found: Mail[] = [];
  await expect
    .poll(async () => {
      await nudge?.();
      const response = await request.get(`${box}/api/v1/search`, { params: { query, limit: '50' } });
      const { messages } = (await response.json()) as { messages: Mail[] };
      found = messages.filter((mail) => Date.parse(mail.Created) >= since);
      return found.length;
    }, { message: `${count} mail(s) matching ${query}`, timeout })
    .toBeGreaterThanOrEqual(count);
  return found;
}

/** Opens the mail as its HTML renders, the way a mail client shows it. */
export async function openMail(page: Page, box: string, mail: Mail): Promise<void> {
  await page.goto(`${box}/view/${mail.ID}.html`, { waitUntil: 'load' });
}

/** The JOS layout as rendered: computed colours, the logo and its link, the footer, the card's box. */
export async function readLayout(page: Page) {
  return page.evaluate(() => {
    const card = document.querySelector<HTMLElement>('td.jos-message');
    const logo = document.querySelector<HTMLImageElement>('img[alt="JOS"]');
    const footer = card?.parentElement?.nextElementSibling?.querySelector<HTMLElement>('td');
    const box = card?.getBoundingClientRect();

    return {
      background: getComputedStyle(document.body).backgroundColor,
      cardBackground: card ? getComputedStyle(card).backgroundColor : null,
      cardColor: card ? getComputedStyle(card).color : null,
      logoPath: logo ? new URL(logo.src).pathname : null,
      logoLoaded: !!logo && logo.complete && logo.naturalWidth > 0,
      logoHref: logo?.closest('a')?.href ?? null,
      footer: footer?.innerText.trim() ?? null,
      card: box ? { left: Math.round(box.left), right: Math.round(box.right) } : null,
      pageWidth: document.documentElement.scrollWidth,
    };
  });
}

/** The parts of the layout every mail shares, checked against the design's own values. */
export async function expectJosLayout(page: Page, baseURL: string): Promise<void> {
  const layout = await readLayout(page);
  expect(layout.background, 'lemon background').toBe('rgb(255, 247, 148)');
  expect(layout.cardBackground, 'the message sits on a white card').toBe('rgb(255, 255, 255)');
  expect(layout.cardColor, 'brown text').toBe('rgb(53, 7, 12)');
  expect(layout.logoPath).toBe('/content/themes/josworld/images/logo.png');
  expect(layout.logoLoaded, 'the logo image loads').toBe(true);
  expect(layout.logoHref, 'the logo links to the home page').toBe(new URL('/', baseURL).href);
  expect(layout.footer).toBe('JOS · jos.agency');
}

/** WP-CLI on the DDEV site the browser drives; local only. */
export function ddevWp(php: string): string {
  return execFileSync('ddev', ['wp', 'eval', php], { encoding: 'utf8' }).trim();
}

/** A literal for PHP code, from a value this suite generated itself. */
export function phpString(value: string): string {
  if (!/^[\w.@+-]+$/.test(value)) {
    throw new Error(`refusing to pass ${value} to PHP`);
  }
  return `'${value}'`;
}
