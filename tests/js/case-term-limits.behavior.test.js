/**
 * Behaviour of case-term-limits.js: the case screen stops an editor ticking past the client's limits —
 * one domain (radio-like), three diensten (the fourth tick is refused). The server clamps the save either
 * way; this proves the DOM-side promise.
 */
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SOURCE = readFileSync(
  resolve(__dirname, '../../app/content/mu-plugins/josworld-core/assets/js/case-term-limits.js'),
  'utf8',
);

function box(taxonomy, id, checked = false) {
  return `<li><label><input type="checkbox" name="tax_input[${taxonomy}][]" value="${id}"${checked ? ' checked' : ''}> ${taxonomy} ${id}</label></li>`;
}

function fixture() {
  document.body.innerHTML = `
    <div id="taxonomy-domain" class="categorydiv"><div class="inside"><ul>${box('domain', 1)}${box('domain', 2)}${box('domain', 3)}</ul></div></div>
    <div id="taxonomy-dienst" class="categorydiv"><div class="inside"><ul>${[1, 2, 3, 4, 5].map((i) => box('dienst', i)).join('')}</ul></div></div>
    <div id="taxonomy-post_tag"><div class="inside"><ul>${box('post_tag', 1)}${box('post_tag', 2)}</ul></div></div>`;
  // wp_localize_script hands numbers over as strings
  window.josworldTermLimits = { domain: '1', dienst: '3' };
  new Function(SOURCE)();
}

const tick = (taxonomy, id) => {
  const input = document.querySelector(`input[name="tax_input[${taxonomy}][]"][value="${id}"]`);
  input.checked = true;
  input.dispatchEvent(new Event('change', { bubbles: true }));
  return input;
};
const checkedIds = (taxonomy) =>
  [...document.querySelectorAll(`input[name="tax_input[${taxonomy}][]"]:checked`)].map((i) => i.value);

// The script delegates on `document`, which outlives a test: take its listeners off again.
const added = [];
const nativeAdd = document.addEventListener.bind(document);

describe('case term limits on the edit screen', () => {
  beforeEach(() => {
    document.addEventListener = (type, handler, options) => {
      added.push([type, handler, options]);
      nativeAdd(type, handler, options);
    };
    fixture();
  });

  afterEach(() => {
    added.forEach(([type, handler, options]) => document.removeEventListener(type, handler, options));
    added.length = 0;
    document.addEventListener = nativeAdd;
  });

  it('ticking a second domain unticks the first', () => {
    tick('domain', 1);
    tick('domain', 2);

    expect(checkedIds('domain')).toEqual(['2']);
  });

  it('refuses the fourth dienst and keeps the first three', () => {
    tick('dienst', 1);
    tick('dienst', 2);
    tick('dienst', 3);
    const fourth = tick('dienst', 4);

    expect(fourth.checked).toBe(false);
    expect(checkedIds('dienst')).toEqual(['1', '2', '3']);
  });

  it('lets a dienst be swapped: untick one, tick another', () => {
    ['1', '2', '3'].forEach((i) => tick('dienst', i));
    const first = document.querySelector('input[name="tax_input[dienst][]"][value="1"]');
    first.checked = false;
    first.dispatchEvent(new Event('change', { bubbles: true }));

    tick('dienst', 4);

    expect(checkedIds('dienst')).toEqual(['2', '3', '4']);
  });

  it('leaves every other taxonomy alone', () => {
    tick('post_tag', 1);
    tick('post_tag', 2);

    expect(checkedIds('post_tag')).toEqual(['1', '2']);
  });

  it('writes the limit under each box, once', () => {
    new Function(SOURCE)();

    const notes = [...document.querySelectorAll('.jw-term-limit-note')].map((n) => n.textContent);
    expect(notes).toEqual(['Kies maximaal 1 domein.', 'Kies maximaal 3 diensten.']);
  });

  it('does nothing without the limits from the server', () => {
    added.forEach(([type, handler, options]) => document.removeEventListener(type, handler, options));
    added.length = 0;
    document.body.innerHTML = `<div id="taxonomy-domain"><div class="inside">${box('domain', 1)}${box('domain', 2)}</div></div>`;
    delete window.josworldTermLimits;
    new Function(SOURCE)();

    tick('domain', 1);
    tick('domain', 2);

    expect(checkedIds('domain')).toEqual(['1', '2']);
  });
});
