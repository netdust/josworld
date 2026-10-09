/**
 * A case has at most ONE domain and at most THREE diensten (client spec). The server clamps whatever is
 * saved (CaseService::clampTerms) — this only stops an editor ticking past the limit, so the screen never
 * promises a choice the save would silently undo.
 *
 * Limits arrive from PHP as window.josworldTermLimits ({ domain: 1, dienst: 3 }). A limit of 1 behaves like
 * a radio group (ticking one unticks the other); a higher limit refuses the tick that would exceed it.
 */
(function () {
	'use strict';

	var NOTES = { domain: 'Kies maximaal %d domein.', dienst: 'Kies maximaal %d diensten.' };

	function boxes(taxonomy) {
		return Array.prototype.slice.call(
			document.querySelectorAll('input[type="checkbox"][name="tax_input[' + taxonomy + '][]"]')
		);
	}

	function onChange(event, limits) {
		var target = event.target;
		var match = target && target.name && /^tax_input\[([a-z_]+)\]\[\]$/.exec(target.name);
		var limit = match ? Number(limits[match[1]]) : 0; // wp_localize_script hands numbers over as strings
		if (!limit || !target.checked) {
			return;
		}

		var checked = boxes(match[1]).filter(function (box) {
			return box.checked;
		});
		if (checked.length <= limit) {
			return;
		}

		if (limit === 1) {
			checked.forEach(function (box) {
				if (box !== target) {
					box.checked = false;
				}
			});
			return;
		}

		target.checked = false;
	}

	function addNotes(limits) {
		Object.keys(limits).forEach(function (taxonomy) {
			var inside = document.querySelector('#taxonomy-' + taxonomy + ' .inside, #' + taxonomy + 'div .inside');
			if (!inside || inside.querySelector('.jw-term-limit-note')) {
				return;
			}
			var note = document.createElement('p');
			note.className = 'description jw-term-limit-note';
			note.textContent = (NOTES[taxonomy] || '').replace('%d', String(limits[taxonomy]));
			inside.appendChild(note);
		});
	}

	function init() {
		var limits = window.josworldTermLimits;
		if (!limits) {
			return;
		}
		document.addEventListener('change', function (event) {
			onChange(event, limits);
		});
		addNotes(limits);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
