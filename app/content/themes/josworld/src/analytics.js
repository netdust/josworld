/**
 * Matomo events for what Auto tracking cannot see: form submissions, bookings
 * and mailto/tel clicks. Pages, outbound links and file downloads are Matomo's own.
 * Everything is pushed to `_paq`, which Matomo reads whenever it is loaded — with
 * tracking off (staging) the pushes go nowhere.
 */

const FORM_NAMES = { 1: 'contact', 2: 'newsletter', 3: 'download', 4: 'stage' };
const BOOKING_ACTION = 'fluent_cal_schedule_meeting';

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
  });

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
