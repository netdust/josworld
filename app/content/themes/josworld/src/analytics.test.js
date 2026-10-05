/**
 * Behavioural tests for src/analytics.js: what reaches Matomo's `_paq` for each
 * visitor action, driven through fake document/window objects (no DOM needed).
 */
import { describe, it, expect } from 'vitest';
import { formEventName, contactLinkType, isBookingRequest, initAnalytics } from './analytics.js';

function setup() {
  const listeners = {};
  const doc = {
    addEventListener: (type, fn) => { listeners[type] = fn; },
    querySelector: () => ({ textContent: '  Canvas - Gedrag ' }),
  };
  class FakeXhr {
    static log = [];
    addEventListener(type, fn) { this.onLoad = fn; }
    send(body) { FakeXhr.log.push(body); }
  }
  const win = { XMLHttpRequest: FakeXhr };
  initAnalytics({ win, doc });
  return { win, listeners, FakeXhr };
}

const anchor = (href) => ({ closest: () => ({ getAttribute: () => href }) });

describe('analytics events', () => {
  it('names forms by id, and a download by the page it sits on', () => {
    expect(formEventName('2')).toBe('newsletter');
    expect(formEventName('3', 'Canvas')).toBe('download: Canvas');
    expect(formEventName('9')).toBe('form-9');
  });

  it('recognises only mailto and tel links', () => {
    expect(contactLinkType('mailto:a@b.be')).toBe('mailto');
    expect(contactLinkType('TEL:+3212')).toBe('tel');
    expect(contactLinkType('https://x.be')).toBeNull();
  });

  it('recognises the booking request in form data and in a query string', () => {
    const fd = new Map([['action', 'fluent_cal_schedule_meeting']]);
    expect(isBookingRequest(fd)).toBe(true);
    expect(isBookingRequest('a=1&action=fluent_cal_schedule_meeting')).toBe(true);
    expect(isBookingRequest('action=other')).toBe(false);
  });

  it('pushes a Form event when FluentForm reports a successful submission', () => {
    const { win, listeners } = setup();
    listeners.fluentform_submission_success({ detail: { form: { dataset: { form_id: '3' } } } });
    expect(win._paq).toEqual([['trackEvent', 'Form', 'Submit', 'download: Canvas - Gedrag']]);
  });

  it('pushes a Contact event for a mailto click and nothing for other links', () => {
    const { win, listeners } = setup();
    listeners.click({ target: anchor('https://jos.agency/team/') });
    listeners.click({ target: anchor('mailto:thijs@jos.agency') });
    expect(win._paq).toEqual([['trackEvent', 'Contact', 'Click', 'mailto']]);
  });

  it('counts a booking only when the request succeeds', () => {
    const { win, FakeXhr } = setup();
    const ok = new FakeXhr();
    ok.send('action=fluent_cal_schedule_meeting');
    ok.status = 200; ok.onLoad();
    const refused = new FakeXhr();
    refused.send('action=fluent_cal_schedule_meeting');
    refused.status = 422; refused.onLoad();
    expect(win._paq).toEqual([['trackEvent', 'Booking', 'Booked', 'sparmoment']]);
    expect(FakeXhr.log).toHaveLength(2);
  });
});
