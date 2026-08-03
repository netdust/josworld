/**
 * Metabox Conditional Fields - implements the documented `'condition' =>
 * ['field' => value]` field option (ntdst-data/references/metabox.md
 * "Conditional Fields") for josworld's own CPT edit screens.
 *
 * ntdst-core's MetaboxGenerator (which this project does not modify) has
 * NO knowledge of `condition` at all — it renders every field visible,
 * always. This script is the entire implementation of the option: it reads
 * the per-post-type condition map localized by
 * AdminUIService::enqueueConditionalFieldScript() (window.josworldConditionalFields)
 * and, at runtime, hides a conditional field's wrapper until its
 * controlling field's value matches.
 *
 * TWO RULES THAT MUST NEVER BE VIOLATED (T11 acceptance criteria):
 *
 * 1. DISCLOSURE IS PRESENTATION-ONLY. Hiding sets `display:none` on the
 *    outer `.ntdst-field` wrapper — it NEVER removes, disables, or renames
 *    any input inside it. A value already entered in a hidden block must
 *    still submit with the form and round-trip on save; see
 *    CaseServiceEditorScreenTest::
 *    test_a_value_in_a_hidden_conditional_block_still_round_trips_on_save
 *    for the server-side half of this same contract.
 *
 * 2. DEGRADE-TO-VISIBLE. Every field MetaboxGenerator renders starts
 *    visible in the DOM (no framework-side `display:none`). This script
 *    only ever ADDS hiding at runtime for an unmet condition — if this
 *    file never runs (JS disabled, script blocked, error before init),
 *    every conditional field is simply left in its default visible state.
 *    A CSS-first "hidden by default, revealed by JS" approach is
 *    deliberately NOT used, because it would invert this and make every
 *    conditional field unreachable without JS.
 *
 * FIELD RESOLUTION: every field MetaboxGenerator renders (any type) is
 * wrapped in `<div class="ntdst-field">…</div>` (MetaboxGenerator.php
 * render_field(), unconditional for every type including repeater/gallery/
 * relation) and carries a stable `id="ntdst_field_{name}"` on its primary
 * control (or, for compound fields like the repeater, on a `<label
 * for="ntdst_field_{name}">` sibling within the same wrapper) — see
 * render_field()'s `$field_id = "ntdst_field_{$name}"` and
 * `render_repeater_field()`'s `data-field-name="{name}"` container. This
 * script resolves a field name to its wrapper via `#ntdst_field_{name}`
 * (id OR for-target) first, falling back to `[data-field-name="{name}"]`
 * for compound field types, then walks up to the nearest `.ntdst-field`.
 *
 * Requires: jQuery, window.josworldConditionalFields (localized by
 * AdminUIService::enqueueConditionalFieldScript()).
 */
(function ($) {
	'use strict';

	if (!$) {
		return;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		const config = window.josworldConditionalFields;
		if (!config || !config.conditions || typeof config.conditions !== 'object') {
			return;
		}

		const conditions = config.conditions;
		const fieldNames = Object.keys(conditions);
		if (fieldNames.length === 0) {
			return;
		}

		fieldNames.forEach(function (fieldName) {
			const rule = conditions[fieldName];
			if (!rule || typeof rule !== 'object') {
				return;
			}

			const controllingFieldName = Object.keys(rule)[0];
			if (!controllingFieldName) {
				return;
			}

			const expectedValue = rule[controllingFieldName];
			const wrapper = findFieldWrapper(fieldName);
			const controllingControl = findFieldControl(controllingFieldName);

			if (!wrapper || !controllingControl) {
				// Nothing to wire up on this screen (e.g. a different post
				// type's fields showed up in the map) — leave the field
				// visible rather than guess, per the degrade-to-visible rule.
				return;
			}

			const evaluate = function () {
				setVisible(wrapper, readControlValue(controllingControl) === expectedValue);
			};

			// Re-evaluate on every user interaction with the controlling
			// field, not just once at load — the field must react live.
			// Native addEventListener rather than jQuery's .on(): this
			// module only needs change/input listening, which jQuery adds
			// nothing to here, and it keeps the module testable without a
			// jQuery dependency in the test environment.
			controllingControl.addEventListener('change', evaluate);
			controllingControl.addEventListener('input', evaluate);

			// Evaluate once immediately so the initial state (which may
			// hide the field) is applied — presentation-only, the inputs
			// inside `wrapper` are never touched here.
			evaluate();
		});
	}

	/**
	 * Resolve a field's OWN control element (used to read the controlling
	 * field's current value). Checkboxes/inputs carry
	 * `id="ntdst_field_{name}"` directly.
	 */
	function findFieldControl(fieldName) {
		return document.getElementById('ntdst_field_' + fieldName);
	}

	/**
	 * Resolve the `.ntdst-field` wrapper that must be hidden/shown for a
	 * given field name — works for simple fields (id-based) and compound
	 * fields like repeaters (data-field-name-based).
	 */
	function findFieldWrapper(fieldName) {
		const byId = document.getElementById('ntdst_field_' + fieldName);
		if (byId) {
			return byId.closest('.ntdst-field');
		}

		const byDataAttr = document.querySelector('[data-field-name="' + fieldName + '"]');
		if (byDataAttr) {
			return byDataAttr.closest('.ntdst-field');
		}

		return null;
	}

	/** Read a control's current logical value (checkbox → boolean, else string). */
	function readControlValue(control) {
		if (control.type === 'checkbox') {
			return control.checked;
		}
		return control.value;
	}

	/**
	 * Toggle visibility WITHOUT touching anything inside `wrapper` — no
	 * input is ever disabled, removed, or renamed. Only the wrapper's own
	 * `display` is set, which is exactly why a value already present
	 * inside a hidden wrapper still submits with the form.
	 */
	function setVisible(wrapper, visible) {
		wrapper.style.display = visible ? '' : 'none';
	}
})(typeof jQuery !== 'undefined' ? jQuery : null);
