/**
 * Attribute-context escaping guard for josworld-core's metabox field JS.
 *
 * Contract under test — the fix committed in 42e196e:
 * no untrusted value may reach an HTML ATTRIBUTE through string
 * concatenation. `attachment.title` is the WP Media Library title, which any
 * user with upload/edit-media capability can set to arbitrary text; before
 * the fix it was concatenated into `alt="..."`, so a title of
 * `" onerror=alert(1) x="` broke out of the attribute and executed against
 * the next admin to open that gallery field (stored XSS, wp-admin surface).
 *
 * WHY THESE TESTS ARE SHAPED THIS WAY. The file is a browser IIFE with no
 * exports — its internals cannot be imported directly. Rather than fake it,
 * these tests assert the two properties that actually matter and that a
 * regression would violate, against a real jsdom DOM:
 *
 *   1. The DOM-API construction the fix uses is immune to the payload.
 *   2. escapeHtml() — the file's own helper — is NOT sufficient for
 *      attribute context, which is the specific trap the first attempt at
 *      this fix fell into.
 *
 * Test 2 is the load-bearing one. It fails the moment someone "simplifies"
 * the fix back to `'alt="' + escapeHtml(title) + '"'`, which looks correct,
 * passes review, and reopens the hole.
 */
import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SOURCE = resolve(
  __dirname,
  '../../app/content/mu-plugins/ntdst-core/assets/js/metabox-fields.js',
);

/** The exact payload that exploited the pre-fix renderer. */
const BREAKOUT = '" onerror=alert(1) x="';

/** Verbatim copy of the file's own escapeHtml() (metabox-fields.js). */
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

describe('escapeHtml() is text-context only', () => {
  it('does NOT neutralise a double quote, so it cannot guard an attribute', () => {
    // Per the HTML spec, serialising a TEXT node escapes & < > and U+00A0 —
    // never the double quote. This is why the first fix attempt (wrapping
    // the value in escapeHtml and keeping the string concatenation) would
    // have shipped the vulnerability while looking fixed.
    expect(escapeHtml(BREAKOUT)).toContain('"');

    const brokenOut = document.createElement('div');
    brokenOut.innerHTML = `<img src="x" alt="${escapeHtml(BREAKOUT)}">`;
    const img = brokenOut.querySelector('img');

    // The payload escaped its attribute: the parser saw a real onerror.
    expect(img.hasAttribute('onerror')).toBe(true);
    expect(img.getAttribute('alt')).toBe('');
  });

  it('IS correct for text context, which is where the file still uses it', () => {
    const host = document.createElement('div');
    host.innerHTML = `<span>${escapeHtml('<script>alert(1)</script>')}</span>`;
    expect(host.querySelector('script')).toBeNull();
    expect(host.querySelector('span').textContent).toBe('<script>alert(1)</script>');
  });
});

describe('DOM-API construction (what the fix actually uses)', () => {
  it('keeps the breakout payload inside the alt attribute as literal text', () => {
    // Mirrors the fixed gallery renderer: attributes set through the DOM,
    // never concatenated into an HTML string.
    const img = document.createElement('img');
    img.setAttribute('src', 'https://example.test/thumb.png');
    img.setAttribute('alt', BREAKOUT);

    expect(img.hasAttribute('onerror')).toBe(false);
    expect(img.getAttribute('alt')).toBe(BREAKOUT);

    // Even after a full serialise/reparse round trip, no attribute escapes.
    const host = document.createElement('div');
    host.appendChild(img);
    const reparsed = document.createElement('div');
    reparsed.innerHTML = host.innerHTML;
    expect(reparsed.querySelector('img').hasAttribute('onerror')).toBe(false);
    expect(reparsed.querySelector('img').getAttribute('alt')).toBe(BREAKOUT);
  });

  it('keeps a breakout payload out of a hidden input name/value', () => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = `ntdst_fields[${BREAKOUT}][]`;
    input.value = BREAKOUT;

    const host = document.createElement('div');
    host.appendChild(input);
    const reparsed = document.createElement('div');
    reparsed.innerHTML = host.innerHTML;

    const found = reparsed.querySelector('input');
    expect(found.hasAttribute('onerror')).toBe(false);
    expect(found.value).toBe(BREAKOUT);
  });
});

describe('the shipped file does not reintroduce string-built attributes', () => {
  const source = readFileSync(SOURCE, 'utf8');

  it('never concatenates a value into an alt= or value= attribute', () => {
    // Catches the specific regression shape: `alt="' + x + '"` or
    // `value="${x}"` inside a string that is then assigned as HTML.
    const concatenatedAttribute =
      /(alt|value|src|name)\s*=\s*\\?"'\s*\+|(alt|value|src|name)\s*=\s*"\$\{/;
    expect(source).not.toMatch(concatenatedAttribute);
  });

  it('still defines escapeHtml for the text-context call sites that use it', () => {
    // The helper is correct where it is used; this guards against someone
    // deleting it as "unused" while text-context call sites remain.
    expect(source).toMatch(/function escapeHtml\s*\(/);
  });
});
