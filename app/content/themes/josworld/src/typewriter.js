/**
 * Josworld — cycling typewriter.
 *
 * NOTE: this is NOT the `typewriter` from the ntdst scroll-effects library. That
 * one is a scroll-driven word-by-word reveal (`.type-word.active`); this types a
 * phrase, holds it, deletes it and moves to the next — the hero's rotating
 * audience line ("Overheid," → "Duurzame ondernemers," → …).
 *
 * The state machine is a pure function so the timing/sequencing is testable
 * without a DOM or fake timers; the Alpine component is a thin shell that just
 * applies each step and sleeps.
 */

/**
 * @typedef {{ index: number, text: string, phase: 'typing'|'holding'|'deleting' }} TypeState
 * @typedef {{ typeMs: number, deleteMs: number, holdMs: number }} TypeConfig
 */

/** @type {TypeConfig} */
export const DEFAULT_TIMING = {
  typeMs: 65,
  deleteMs: 35,
  holdMs: 1600,
};

/**
 * Compute the next state of the machine.
 *
 * Deliberately total: any state advances, and an empty word list is a no-op
 * rather than an exception, so a mis-configured element degrades to static text
 * instead of throwing on every tick.
 *
 * @param {TypeState} state
 * @param {string[]} words
 * @param {TypeConfig} [timing]
 * @returns {{ state: TypeState, delay: number }}
 */
export function advance(state, words, timing = DEFAULT_TIMING) {
  if (!words.length) {
    return { state, delay: timing.holdMs };
  }

  const index = state.index % words.length;
  const word = words[index];

  if (state.phase === 'typing') {
    if (state.text.length < word.length) {
      const text = word.slice(0, state.text.length + 1);
      // Reached the end on this very step — hold rather than spending a whole
      // extra tick discovering there is nothing left to type.
      return text.length === word.length
        ? { state: { index, text, phase: 'holding' }, delay: timing.holdMs }
        : { state: { index, text, phase: 'typing' }, delay: timing.typeMs };
    }
    return { state: { index, text: word, phase: 'holding' }, delay: timing.holdMs };
  }

  if (state.phase === 'holding') {
    return { state: { index, text: state.text, phase: 'deleting' }, delay: timing.deleteMs };
  }

  // deleting
  if (state.text.length > 0) {
    const text = state.text.slice(0, -1);
    return text.length === 0
      ? { state: { index: (index + 1) % words.length, text: '', phase: 'typing' }, delay: timing.typeMs }
      : { state: { index, text, phase: 'deleting' }, delay: timing.deleteMs };
  }

  return {
    state: { index: (index + 1) % words.length, text: '', phase: 'typing' },
    delay: timing.typeMs,
  };
}

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Alpine component. Register with:
 *   Alpine.data('typewriter', typewriter)
 *
 * Usage in the builder (an HTML element is enough — no PHP, no custom element):
 *   <span x-data="typewriter({ words: ['Overheid,', 'Social profit,'] })"
 *         x-text="text"></span>
 */
export function typewriter(options = {}) {
  const words = Array.isArray(options.words) ? options.words.filter(Boolean) : [];
  const timing = { ...DEFAULT_TIMING, ...options };

  return {
    text: '',
    running: false,

    init() {
      if (!words.length) {
        return;
      }

      // Reduced motion: show the first phrase and stop. The line still reads
      // correctly, it just doesn't animate.
      const reduce =
        typeof window !== 'undefined' &&
        window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

      if (reduce) {
        this.text = words[0];
        return;
      }

      this.running = true;
      this.$el.addEventListener('x-destroy', () => {
        this.running = false;
      });
      this.loop();
    },

    async loop() {
      let state = { index: 0, text: '', phase: 'typing' };

      while (this.running) {
        const next = advance(state, words, timing);
        state = next.state;
        this.text = state.text;
        await sleep(next.delay);

        // The element can be swapped out under us (builder preview, page
        // transitions); stop rather than tick against a detached node.
        if (!this.$el || !this.$el.isConnected) {
          this.running = false;
        }
      }
    },
  };
}
