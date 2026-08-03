/**
 * T11 behavioral contract for metabox-conditional.js — the `condition`
 * field option documented at ntdst-data/references/metabox.md:190 and
 * implemented nowhere upstream (0 hits across all deployed ntdst-core
 * copies). This file is OUR code; nothing upstream proves it works.
 *
 * THE CONTRACT (tasks.md T11 Integration test line, JS half):
 *  1. A field carrying `'condition' => ['field' => value]` is HIDDEN until
 *     its controlling field matches.
 *  2. Hiding is presentation-only: the controlled inputs are never removed
 *     or disabled, so a value already present in a hidden block still
 *     submits with the form (the round-trip half of this contract is
 *     proven at the PHP/DB layer by
 *     CaseServiceEditorScreenTest::test_a_value_in_a_hidden_conditional_block_still_round_trips_on_save;
 *     THIS file proves the DOM-side half — nothing here strips the input).
 *  3. DEGRADE-TO-VISIBLE: with the script never run at all (the "JS
 *     disabled" case), every conditional field must already be visible —
 *     the markup MetaboxGenerator renders (which this project does not
 *     modify) has no `display:none` of its own, so the only way a field
 *     is ever hidden is this script adding it at runtime. This test
 *     asserts that starting DOM state directly.
 *
 * WHY THESE TESTS ARE SHAPED THIS WAY: the file is a browser IIFE with no
 * module exports (matching metabox-fields.js's own shape/pattern in this
 * project) and self-initializes on DOMContentLoaded. Rather than reach for
 * internals that don't exist, these tests load the real source into a real
 * jsdom document (fixture markup shaped exactly like MetaboxGenerator's
 * actual output) and assert the OBSERTABLE DOM contract: what the browser
 * would see, before and after the module runs.
 */
import { describe, it, expect, beforeEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SOURCE_PATH = resolve(
  __dirname,
  '../../app/content/mu-plugins/josworld-core/assets/js/metabox-conditional.js',
);
const SOURCE = readFileSync(SOURCE_PATH, 'utf8');

/**
 * Build fixture markup shaped exactly like MetaboxGenerator::render_field()'s
 * real output: each field wrapped in `<div class="ntdst-field">`, checkbox
 * id/name convention `ntdst_field_{name}` / `ntdst_fields[{name}]`, textarea
 * likewise — see MetaboxGenerator.php render_field() (boolean + textarea
 * cases) and render_repeater_field() (data-field-name).
 */
function buildFixture() {
  document.body.innerHTML = `
    <form>
      <div class="ntdst-field">
        <label for="ntdst_field_has_takeaways">Heeft take-aways?</label>
        <label><input type="checkbox" id="ntdst_field_has_takeaways" name="ntdst_fields[has_takeaways]" value="1"> Yes</label>
      </div>
      <div class="ntdst-field">
        <label for="ntdst_field_takeaways">Take-aways</label>
        <div class="ntdst-repeater-field" data-field-name="takeaways">
          <input type="hidden" name="ntdst_fields[takeaways][0][title]" value="Lesson one" class="takeaways-input">
          <textarea name="ntdst_fields[takeaways][0][body]" class="takeaways-input">Detail that must survive</textarea>
        </div>
      </div>
    </form>
  `;

  window.josworldConditionalFields = {
    conditions: {
      takeaways: { has_takeaways: true },
    },
  };

  // The module's IIFE gates on `typeof jQuery !== 'undefined'` (matching
  // metabox-fields.js's own bail-out convention), but its actual logic
  // uses only native DOM APIs — see the module's own comment on why. A
  // minimal stub is enough to pass that gate without pulling in the real
  // jQuery library.
  window.jQuery = window.jQuery || function stubJQuery() {};
}

/**
 * Load and execute the real module source against the current jsdom
 * globals. Vitest's jsdom pool does not execute injected `<script>` tags
 * (that needs jsdom's `runScripts: 'dangerously'`, which the pool does not
 * enable) — so the source is evaluated directly via `new Function`,
 * against the SAME `window`/`document`/`jQuery` globals the test's DOM
 * assertions read afterwards. This executes the exact shipped file
 * (`readFileSync`, no rewriting), just via a different loader than a real
 * browser's `<script src>` tag would use.
 */
function runModule() {
  // eslint-disable-next-line no-new-func
  const executeSource = new Function('window', 'document', 'jQuery', SOURCE);
  executeSource(window, document, window.jQuery);
  // The module waits for DOMContentLoaded when the document is still
  // loading. jsdom's readyState here is already 'complete', so init() runs
  // synchronously inside executeSource() above — but dispatch the event too
  // in case the module is later changed to always listen for it.
  document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true, cancelable: true }));
}

function takeawaysWrapper() {
  return document.getElementById('ntdst_field_takeaways')
    ? document.getElementById('ntdst_field_takeaways').closest('.ntdst-field')
    : document.querySelector('[data-field-name="takeaways"]').closest('.ntdst-field');
}

function isVisuallyHidden(el) {
  return el.style.display === 'none';
}

describe('with the script never run (JS disabled)', () => {
  beforeEach(() => {
    buildFixture();
  });

  it('leaves every conditional field visible and its inputs present and enabled', () => {
    // No runModule() call — this is the "JS disabled" baseline.
    const wrapper = takeawaysWrapper();
    expect(isVisuallyHidden(wrapper)).toBe(false);

    document.querySelectorAll('.takeaways-input').forEach((input) => {
      expect(input.disabled).toBe(false);
    });
  });
});

describe('metabox-conditional.js — condition evaluation', () => {
  beforeEach(() => {
    buildFixture();
  });

  it('hides a conditional field when its controlling checkbox is unchecked', () => {
    runModule();

    const wrapper = takeawaysWrapper();
    expect(isVisuallyHidden(wrapper)).toBe(true);
  });

  it('reveals the conditional field when the controlling checkbox is checked', () => {
    runModule();

    const checkbox = document.getElementById('ntdst_field_has_takeaways');
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));

    const wrapper = takeawaysWrapper();
    expect(isVisuallyHidden(wrapper)).toBe(false);
  });

  it('re-hides the field when the checkbox is unchecked again', () => {
    runModule();

    const checkbox = document.getElementById('ntdst_field_has_takeaways');
    checkbox.checked = true;
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    expect(isVisuallyHidden(takeawaysWrapper())).toBe(false);

    checkbox.checked = false;
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
    expect(isVisuallyHidden(takeawaysWrapper())).toBe(true);
  });

  it('NEVER removes or disables the inputs inside a hidden conditional block (disclosure must not drop data)', () => {
    runModule();

    // Field starts hidden (has_takeaways unchecked) — this is the exact
    // state a naive "strip hidden inputs" implementation would break.
    expect(isVisuallyHidden(takeawaysWrapper())).toBe(true);

    const inputs = document.querySelectorAll('.takeaways-input');
    expect(inputs.length).toBe(2);
    inputs.forEach((input) => {
      expect(input.disabled).toBe(false);
      expect(document.body.contains(input)).toBe(true);
    });

    // The values entered before the block was hidden are still intact —
    // this is what "still round-trips on save" depends on client-side.
    expect(document.querySelector('input[name="ntdst_fields[takeaways][0][title]"]').value).toBe(
      'Lesson one',
    );
    expect(
      document.querySelector('textarea[name="ntdst_fields[takeaways][0][body]"]').value,
    ).toBe('Detail that must survive');
  });

  it('does nothing when no conditions are localized (no controlling field present)', () => {
    window.josworldConditionalFields = { conditions: {} };
    expect(() => runModule()).not.toThrow();
    expect(isVisuallyHidden(takeawaysWrapper())).toBe(false);
  });
});
