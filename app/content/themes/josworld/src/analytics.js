/**
 * Matomo events for what Auto tracking cannot see: form submissions, bookings,
 * mailto/tel clicks, call-to-action buttons and how far down a page people read.
 * Pages, outbound links and file downloads are Matomo's own.
 * Everything is pushed to `_paq`, which Matomo reads whenever it is loaded — with
 * tracking off (staging) the pushes go nowhere.
 */

const FORM_NAMES = { 1: 'contact', 2: 'newsletter', 3: 'download', 4: 'stage' };
const BOOKING_ACTION = 'fluent_cal_schedule_meeting';
const SCROLL_STEPS = [25, 50, 75, 100];
const CTA_SELECTOR = 'a.uk-button, button.uk-button';

export function formEventName(formId, heading = '') {
  const name = FORM_NAMES[formId] ?? `form-${formId}`;
  return name === 'download' && heading ? `${name}: ${heading}` : name;
}

export function contactLinkType(href = '') {
  const match = /^(mailto|tel):/i.exec(href);
  return match ? match[1].toLowerCase() : null;
}

export function isBookingRequest(body) {
  if (body && typeof body.get === 'function') return body.get('action') === BOOKING_ACTION;
  return typeof body === 'string' && body.includes(`action=${BOOKING_ACTION}`);
}

/** The scroll depths (in percent) newly crossed at `ratio` of the page, given those already reported. */
export function scrollMilestones(ratio, reached) {
  return SCROLL_STEPS.filter((step) => ratio * 100 >= step && !reached.has(step));
}

/** A call-to-action button as an event: its label and where it leads. Null for anything else. */
export function ctaEvent(target) {
  const button = target?.closest?.(CTA_SELECTOR);
  if (!button) return null;
  const label = (button.textContent ?? '').replace(/\s+/g, ' ').trim().slice(0, 60);
  const href = button.getAttribute?.('href') ?? '';
  const where = /^(https?:)?\/\//.test(href) ? new URL(href, 'https://x').pathname : href.split('#')[0] || href;
  return label ? ['trackEvent', 'CTA', 'Click', `${label} → ${where || '#'}`] : null;
}

export function initAnalytics({ win = window, doc = document } = {}) {
  const track = (category, action, name) => {
    (win._paq ||= []).push(['trackEvent', category, action, name]);
  };

  doc.addEventListener('fluentform_submission_success', (event) => {
    const form = event.detail?.form;
    const id = form?.dataset?.form_id;
    track('Form', 'Submit', formEventName(id, doc.querySelector('h1')?.textContent?.trim()));
  });

  doc.addEventListener('click', (event) => {
    const type = contactLinkType(event.target?.closest?.('a')?.getAttribute('href') ?? '');
    if (type) track('Contact', 'Click', type);

    const cta = ctaEvent(event.target);
    if (cta) (win._paq ||= []).push(cta);
  });

  const reached = new Set();
  win.addEventListener?.('scroll', () => {
    const height = doc.documentElement?.scrollHeight;
    if (!height) return;
    for (const step of scrollMilestones((win.scrollY + win.innerHeight) / height, reached)) {
      reached.add(step);
      track('Scroll', 'Depth', `${step}%`);
    }
  }, { passive: true });

  // FluentBooking confirms in its own screen and fires no event: watch its AJAX call. Failures are HTTP 422.
  const Xhr = win.XMLHttpRequest;
  if (!Xhr) return;
  const send = Xhr.prototype.send;
  Xhr.prototype.send = function (body) {
    if (isBookingRequest(body)) {
      this.addEventListener('load', () => {
        if (this.status === 200) track('Booking', 'Booked', 'sparmoment');
      });
    }
    return send.call(this, body);
  };
}
