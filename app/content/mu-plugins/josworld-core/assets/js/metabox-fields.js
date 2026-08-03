/**
 * Metabox Fields - All field interactions for NTDST MetaboxGenerator
 *
 * Handles:
 * - Relation field autocomplete (posts/users)
 * - Gallery field (media library + sortable)
 * - Repeater field (add/remove rows + sortable)
 *
 * Requires: ntdstAPI (from ntdst-api.js), jQuery, jQuery UI Sortable
 * @version 2.0.0
 */

(function($) {
	'use strict';

	// Bail out early if jQuery is not available (frontend without jQuery)
	if (!$) {
		return;
	}

	// Wait for DOM ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	function init() {
		initRelationFields();
		initGalleryFields();
		initRepeaterFields();
	}

	// =========================================================================
	// RELATION FIELDS - Autocomplete for posts/users
	// =========================================================================

	function initRelationFields() {
		// Check if ntdstAPI is available
		if (typeof window.ntdstAPI === 'undefined') {
			console.warn('MetaboxFields: ntdstAPI not found. Relation fields disabled.');
			return;
		}

		const relationFields = document.querySelectorAll('.ntdst-relation-field');
		relationFields.forEach(field => initRelationField(field));
	}

	function initRelationField(fieldContainer) {
		const fieldName = fieldContainer.dataset.fieldName;
		const postType = fieldContainer.dataset.postType;
		const userRole = fieldContainer.dataset.userRole;
		const allowMultiple = fieldContainer.dataset.multiple === '1';

		const searchInput = fieldContainer.querySelector('.ntdst-relation-input');
		const resultsContainer = fieldContainer.querySelector('.ntdst-relation-results');
		const selectedContainer = fieldContainer.querySelector('.ntdst-relation-selected');

		if (!searchInput || !resultsContainer || !selectedContainer) {
			console.warn('MetaboxFields: Missing elements in relation field:', fieldName);
			return;
		}

		let searchTimeout = null;
		const isUserSearch = postType === 'user';

		// Handle search input
		searchInput.addEventListener('input', function(e) {
			const query = e.target.value.trim();

			if (searchTimeout) clearTimeout(searchTimeout);

			if (query.length < 2) {
				hideResults(resultsContainer);
				return;
			}

			searchTimeout = setTimeout(() => {
				if (isUserSearch) {
					searchUsers(query, userRole, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName);
				} else {
					searchPosts(query, postType, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName);
				}
			}, 300);
		});

		// Hide results when clicking outside
		document.addEventListener('click', function(e) {
			if (!fieldContainer.contains(e.target)) {
				hideResults(resultsContainer);
			}
		});

		// Handle remove buttons
		selectedContainer.addEventListener('click', function(e) {
			if (e.target.classList.contains('ntdst-relation-remove')) {
				const tag = e.target.closest('.ntdst-relation-tag');
				if (tag) tag.remove();
			}
		});
	}

	async function searchPosts(query, postType, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName) {
		resultsContainer.style.display = 'block';
		resultsContainer.innerHTML = '<div class="ntdst-relation-result-loading">Searching...</div>';

		try {
			const response = await window.ntdstAPI.call('search_posts', {
				search: query,
				post_types: [postType],
				per_page: 10
			});

			if (response.error) {
				showError(resultsContainer, response.error);
				return;
			}

			const results = (response.results || []).map(post => ({
				id: post.ID || post.id,
				title: post.post_title || post.title
			}));

			renderResults(results, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName);
		} catch (error) {
			showError(resultsContainer, 'Search failed');
		}
	}

	async function searchUsers(query, userRole, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName) {
		resultsContainer.style.display = 'block';
		resultsContainer.innerHTML = '<div class="ntdst-relation-result-loading">Searching...</div>';

		try {
			const response = await window.ntdstAPI.call('search_users', {
				search: query,
				role: userRole || '',
				per_page: 10
			});

			if (response.error) {
				showError(resultsContainer, response.error);
				return;
			}

			const results = (response.results || []).map(user => ({
				id: user.ID || user.id,
				title: user.post_title || user.title
			}));

			renderResults(results, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName);
		} catch (error) {
			showError(resultsContainer, 'Search failed');
		}
	}

	function renderResults(results, resultsContainer, selectedContainer, searchInput, allowMultiple, fieldName) {
		if (results.length === 0) {
			resultsContainer.innerHTML = '<div class="ntdst-relation-result-empty">No results found</div>';
			return;
		}

		resultsContainer.innerHTML = '';

		results.forEach(result => {
			const item = document.createElement('div');
			item.className = 'ntdst-relation-result-item';
			item.textContent = result.title;
			item.dataset.id = result.id;

			item.addEventListener('click', function() {
				addSelectedItem(result.id, result.title, selectedContainer, allowMultiple, fieldName);
				hideResults(resultsContainer);
				searchInput.value = '';
			});

			resultsContainer.appendChild(item);
		});
	}

	function addSelectedItem(id, title, selectedContainer, allowMultiple, fieldName) {
		if (!allowMultiple) {
			selectedContainer.innerHTML = '';
		}

		if (selectedContainer.querySelector(`[data-id="${id}"]`)) {
			return;
		}

		const tag = document.createElement('span');
		tag.className = 'ntdst-relation-tag';
		tag.dataset.id = id;
		tag.innerHTML = `
			${escapeHtml(title)}
			<button type="button" class="ntdst-relation-remove" aria-label="Remove">&times;</button>
			<input type="hidden" name="ntdst_fields[${fieldName}][]" value="${id}">
		`;

		selectedContainer.appendChild(tag);
	}

	function hideResults(resultsContainer) {
		resultsContainer.style.display = 'none';
		resultsContainer.innerHTML = '';
	}

	function showError(resultsContainer, message) {
		resultsContainer.innerHTML = `<div class="ntdst-relation-result-empty" style="color: #d63638;">${escapeHtml(message)}</div>`;
	}

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// =========================================================================
	// GALLERY FIELDS - Media library + sortable
	// =========================================================================

	function initGalleryFields() {
		if (typeof $ === 'undefined' || typeof $.fn.sortable === 'undefined') {
			console.warn('MetaboxFields: jQuery UI Sortable not found. Gallery sorting disabled.');
			return;
		}

		// Make gallery sortable
		$('.ntdst-gallery-preview').sortable({
			items: '.ntdst-gallery-item',
			cursor: 'move',
			placeholder: 'ui-sortable-placeholder',
			tolerance: 'pointer',
			forcePlaceholderSize: true
		});

		// Remove image
		$(document).on('click', '.ntdst-gallery-remove', function(e) {
			e.preventDefault();
			$(this).closest('.ntdst-gallery-item').remove();
		});

		// Add images via WordPress Media Library
		$(document).on('click', '.ntdst-gallery-add', function(e) {
			e.preventDefault();

			const button = $(this);
			const fieldId = button.data('field-id');
			const preview = $('#' + fieldId + '_preview');
			const fieldName = preview.closest('.ntdst-gallery-field').data('field-name');

			const frame = wp.media({
				title: 'Select Images',
				button: { text: 'Add to Gallery' },
				multiple: true,
				library: { type: 'image' }
			});

			frame.on('select', function() {
				const selection = frame.state().get('selection');

				selection.each(function(attachment) {
					attachment = attachment.toJSON();

					if (preview.find('[data-id="' + attachment.id + '"]').length > 0) {
						return;
					}

					const thumbUrl = attachment.sizes && attachment.sizes.thumbnail
						? attachment.sizes.thumbnail.url
						: attachment.url;

					const item = $('<div>')
						.addClass('ntdst-gallery-item')
						.attr('data-id', attachment.id)
						.html(
							'<img src="' + thumbUrl + '" alt="' + attachment.title + '">' +
							'<button type="button" class="ntdst-gallery-remove" aria-label="Remove">&times;</button>' +
							'<input type="hidden" name="ntdst_fields[' + fieldName + '][]" value="' + attachment.id + '">'
						);

					preview.append(item);
				});

				preview.sortable('refresh');
			});

			frame.open();
		});
	}

	// =========================================================================
	// REPEATER FIELDS - Add/remove rows + sortable
	// =========================================================================

	function initRepeaterFields() {
		if (typeof $ === 'undefined' || typeof $.fn.sortable === 'undefined') {
			console.warn('MetaboxFields: jQuery UI Sortable not found. Repeater sorting disabled.');
			return;
		}

		// Make table rows sortable
		$('.ntdst-repeater-rows').sortable({
			handle: '.ntdst-repeater-drag-handle',
			placeholder: 'ntdst-repeater-row ui-sortable-placeholder',
			axis: 'y',
			cursor: 'move',
			opacity: 0.8,
			helper: function(e, tr) {
				const originals = tr.children();
				const helper = tr.clone();
				helper.children().each(function(index) {
					$(this).width(originals.eq(index).width());
				});
				return helper;
			}
		});

		// Add row
		$(document).on('click', '.ntdst-repeater-add', function() {
			const button = $(this);
			const container = button.closest('.ntdst-repeater-field');
			const fieldId = container.data('field-id');
			const maxRows = container.data('max-rows');
			const rows = container.find('.ntdst-repeater-rows');
			const currentRowCount = rows.children('.ntdst-repeater-row').length;

			if (maxRows && currentRowCount >= maxRows) {
				alert('Maximum number of rows (' + maxRows + ') reached.');
				return;
			}

			const template = $('#' + fieldId + '_template').html();
			const newIndex = currentRowCount;
			const newRow = template.replace(/__INDEX__/g, newIndex);

			rows.append(newRow);
			rows.sortable('refresh');
		});

		// Remove row
		$(document).on('click', '.ntdst-repeater-remove', function() {
			const row = $(this).closest('.ntdst-repeater-row');

			if (confirm('Remove this row?')) {
				row.remove();
			}
		});
	}

})(typeof jQuery !== 'undefined' ? jQuery : null);
