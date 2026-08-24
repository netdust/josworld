/**
 * Josworld — Frontend Entry Point
 *
 * Initializes Alpine.js and a small set of generic UI behaviours.
 * Add brand/feature components below as the site grows.
 */

// NOTE: no CSS is imported here. All styling lives in the YOOtheme LESS style
// (less/theme.josworld.less), compiled by YOOtheme's builder — this bundle is
// JavaScript only.

// Import Alpine.js and plugins
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import { initLogoEye } from './logo-eye.js';
import { typewriter } from './typewriter.js';
import { initScrollEffects } from './effects/scroll-effects.js';

Alpine.plugin(collapse);
window.Alpine = Alpine;

// ══════════════════════════════════════
// GENERIC ALPINE COMPONENTS
// ══════════════════════════════════════

/**
 * Mobile menu toggle. Usage: x-data="mobileMenu()"
 */
Alpine.data('mobileMenu', () => ({
  open: false,
  toggle() {
    this.open = !this.open;
    document.body.classList.toggle('overflow-hidden', this.open);
  },
  close() {
    this.open = false;
    document.body.classList.remove('overflow-hidden');
  },
}));

/**
 * Dropdown menu with click-outside close. Usage: x-data="dropdown()"
 */
Alpine.data('dropdown', () => ({
  open: false,
  toggle() {
    this.open = !this.open;
  },
  close() {
    this.open = false;
  },
}));

/**
 * Expandable card/accordion. Usage: x-data="expandable(true)"
 */
Alpine.data('expandable', (initialOpen = false) => ({
  open: Boolean(initialOpen),
  toggle() {
    this.open = !this.open;
  },
}));

/**
 * Toast notification store.
 * Usage: this.$dispatch('toast', { message: 'Saved!', type: 'success' })
 */
Alpine.data('toastStore', () => ({
  visible: false,
  message: '',
  type: 'success',
  timeout: null,
  show({ message, type = 'success' }) {
    clearTimeout(this.timeout);
    this.message = message;
    this.type = type;
    this.visible = true;
    this.timeout = setTimeout(() => (this.visible = false), 4000);
  },
}));

/**
 * Cycling typewriter for the hero's rotating audience line.
 * Usage: x-data="typewriter({ words: ['Overheid,', 'Social profit,'] })" x-text="text"
 */
Alpine.data('typewriter', typewriter);

/**
 * Loading state wrapper. Usage: x-data="loadingState()"
 */
Alpine.data('loadingState', () => ({
  loading: false,
  async withLoading(callback) {
    this.loading = true;
    try {
      await callback();
    } finally {
      this.loading = false;
    }
  },
}));

// ══════════════════════════════════════
// NTDST API WRAPPER
// ══════════════════════════════════════

/**
 * Thin wrapper around the ntdst-core REST API with automatic nonce handling.
 * Prefer this over raw fetch() for WP endpoints once you wire backend actions.
 *
 * Usage: ntdstAPI.call('my_action', { foo: 'bar' }).then(data => ...)
 */
window.ntdstAPI = {
  _nonceCache: {},

  async _nonce(action) {
    if (this._nonceCache[action]) return this._nonceCache[action];
    const res = await fetch('/wp-json/ntdst/v1/get_nonce', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.josworldConfig?.restNonce || '',
      },
      body: JSON.stringify({ action }),
    });
    const result = await res.json();
    if (!result.success) {
      throw new Error(result.data?.message || 'Kon geen beveiligingstoken ophalen');
    }
    this._nonceCache[action] = result.data.nonce;
    return result.data.nonce;
  },

  async call(action, params = {}, _retried = false) {
    const nonce = await this._nonce(action);
    const res = await fetch('/wp-json/ntdst/v1/action', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': window.josworldConfig?.restNonce || '',
      },
      body: JSON.stringify({ action, nonce, ...params }),
    });
    const result = await res.json();

    // If the nonce expired, clear the cache and retry exactly once; a second
    // invalid_nonce falls through to the error below (no unbounded loop).
    if (!result.success && result.data?.code === 'invalid_nonce' && !_retried) {
      delete this._nonceCache[action];
      return this.call(action, params, true);
    }
    if (!result.success) {
      throw new Error(result.data?.message || 'Actie mislukt');
    }
    return result.data;
  },
};

// ══════════════════════════════════════
// INITIALIZE ALPINE
// ══════════════════════════════════════

Alpine.start();

// ══════════════════════════════════════
// BRAND BEHAVIOUR
// ══════════════════════════════════════

// The JOS mark's eye tracks the cursor (and keeps its own blink). No-ops on
// touch devices and under prefers-reduced-motion.
initLogoEye();

// Lenis smooth scroll + the scroll-linked image/text effects. Under
// prefers-reduced-motion this reveals the content without animating it.
initScrollEffects();

if (window.josworldConfig?.debug) {
  console.log('Josworld frontend initialized');
}
