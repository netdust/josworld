/**
 * Josworld — Lenis smooth scroll + the scroll-linked effect engines.
 *
 * Ported from ludoluykx (`src/effects/`), which is the maintained copy:
 * image-scroll v3.1 and text-scroll v2.1, both unchanged here. Their visual
 * definitions live in `less/components/animations.less`, imported by the
 * YOOtheme style — the JS only computes scroll progress and toggles classes or
 * writes inline transforms.
 *
 * The one thing added for this project is the reduced-motion path, because the
 * port has a trap: `animations.less` hides every effect element with
 * `:not(.js-ready) { visibility: hidden }` to prevent a flash of unstyled
 * content, and the engines are what add `js-ready`. So simply *skipping*
 * initialisation for reduced-motion users would leave the content permanently
 * invisible. `revealAll()` lifts the guard and puts everything in its final
 * state instead.
 */
import Lenis from 'lenis';
import { imageScroll } from './image-scroll.js';
import { textScroll } from './text-scroll.js';

const EFFECT_SELECTOR =
  '.text-scroll-effect, .image-scroll-effect, .image-scroll-sticky, .horizontal-scroll-section';

let lenis = null;
let textScrollInstance = null;
let imageScrollInstance = null;
let rafId = null;

function prefersReducedMotion() {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches
  );
}

/**
 * Put every effect element in its finished state, without animating.
 *
 * Undoes the CSS starting states by hand: the FOUC guard (`js-ready`), the
 * `opacity: 0` on image effects, and the `clip-path: inset(100% …)` on the
 * clip variants. Text effects need nothing beyond the guard — their dimmed
 * `.word` rules only match spans the engine would have created.
 *
 * @param {Document|HTMLElement} root
 */
export function revealAll(root = document) {
  root.querySelectorAll(EFFECT_SELECTOR).forEach((el) => {
    el.classList.add('js-ready');
    el.style.opacity = '1';
    el.style.transform = 'none';
    el.style.filter = 'none';

    el.querySelectorAll('img').forEach((img) => {
      img.style.clipPath = 'none';
      img.style.transform = 'none';
    });

    // If the text engine already split the copy, light every word up.
    el.querySelectorAll('span').forEach((span) => span.classList.add('active'));
  });
}

/**
 * Initialise Lenis + the scroll effects. Safe to call twice, and safe to call
 * where there is no DOM (main.js is imported under Vitest's `node` environment,
 * which aliases `window` to globalThis but has no `document`; Lenis's
 * constructor reaches for `document` immediately).
 */
export function initScrollEffects() {
  if (lenis || typeof document === 'undefined' || typeof window === 'undefined') {
    return;
  }

  if (prefersReducedMotion()) {
    revealAll();
    return;
  }

  lenis = new Lenis({
    duration: 1.2,
    easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    smoothWheel: true,
    // Opt an element out of smooth scrolling (modals, code blocks, maps).
    prevent: (node) => node.closest('[data-lenis-prevent]') !== null,
  });

  const raf = (time) => {
    lenis?.raf(time);
    rafId = requestAnimationFrame(raf);
  };
  rafId = requestAnimationFrame(raf);

  textScrollInstance = textScroll.init({ lenis });
  imageScrollInstance = imageScroll.init({ lenis });
}

/** Tear everything down — the hook a future Barba `beforeLeave` would call. */
export function destroyScrollEffects() {
  if (rafId) {
    cancelAnimationFrame(rafId);
    rafId = null;
  }

  lenis?.destroy();
  lenis = null;

  textScrollInstance?.destroy?.();
  textScrollInstance = null;

  imageScrollInstance?.destroy?.();
  imageScrollInstance = null;
}

/** @param {boolean} instant */
export function scrollToTop(instant = false) {
  if (lenis) {
    lenis.scrollTo(0, { immediate: instant });
  } else {
    window.scrollTo(0, 0);
  }
}

export const getLenis = () => lenis;
