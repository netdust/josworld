/**
 * A case has at most ONE domain, THREE diensten and ONE doelgroep (client spec). The server clamps whatever
 * is saved (CaseService::clampTerms) — this only stops the block editor accepting a tick past the limit, so
 * the screen never promises a choice the save would silently undo.
 *
 * The editor keeps each taxonomy's chosen term ids on the edited post under the taxonomy's rest base
 * (domain, dienst, doelgroep). When one holds more than its limit, the post is edited back to the allowed
 * set: a limit of 1 keeps the term ticked last (radio-like), a higher limit drops the tick that overshot.
 * The limits arrive from PHP as window.josworldTermLimits ({ domain: '1', dienst: '3', doelgroep: '1' }).
 */
(function () {
	'use strict';

	function allowed(ids, limit) {
		return limit === 1 ? ids.slice(-1) : ids.slice(0, limit);
	}

	function init() {
		var limits = window.josworldTermLimits;
		var data = window.wp && window.wp.data;
		if (!limits || !data) {
			return;
		}

		var busy = false;
		data.subscribe(function () {
			var editor = data.select('core/editor');
			if (busy || !editor || editor.getCurrentPostType() !== 'case') {
				return;
			}

			Object.keys(limits).forEach(function (taxonomy) {
				var limit = Number(limits[taxonomy]); // wp_localize_script hands numbers over as strings
				var ids = editor.getEditedPostAttribute(taxonomy);
				if (!limit || !Array.isArray(ids) || ids.length <= limit) {
					return;
				}

				busy = true;
				var edit = {};
				edit[taxonomy] = allowed(ids, limit);
				data.dispatch('core/editor').editPost(edit);
				busy = false;
			});
		});
	}

	init();
})();
