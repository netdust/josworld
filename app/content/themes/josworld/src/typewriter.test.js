/**
 * Behavioural tests for the hero typewriter's state machine (src/typewriter.js).
 *
 * Only `advance` is covered: it holds all the sequencing, so driving it in a
 * loop proves the real observable behaviour (what a visitor sees, in order)
 * without fake timers or a DOM.
 */
import { describe, it, expect } from 'vitest';
import { advance, DEFAULT_TIMING } from './typewriter.js';

const WORDS = ['ab', 'cd'];
const FAST = { typeMs: 1, deleteMs: 2, holdMs: 3 };

/** Run the machine and collect every rendered string, in order. */
function run(words, steps, timing = FAST) {
  let state = { index: 0, text: '', phase: 'typing' };
  const frames = [];
  for (let i = 0; i < steps; i++) {
    const next = advance(state, words, timing);
    state = next.state;
    frames.push({ text: state.text, phase: state.phase, delay: next.delay });
  }
  return frames;
}

describe('advance', () => {
  it('types the first word one character at a time', () => {
    const frames = run(WORDS, 2);
    expect(frames.map((f) => f.text)).toEqual(['a', 'ab']);
  });

  it('holds as soon as the last character lands, without an extra tick', () => {
    // Guards an off-by-one that would show the finished word twice and make the
    // pause feel a beat too long.
    const frames = run(WORDS, 2);
    expect(frames[1]).toMatchObject({ text: 'ab', phase: 'holding', delay: FAST.holdMs });
  });

  it('deletes back to empty, then moves to the next word', () => {
    const frames = run(WORDS, 6);
    expect(frames.map((f) => f.text)).toEqual(['a', 'ab', 'ab', 'a', '', 'c']);
  });

  it('wraps from the last word back to the first', () => {
    // 2 words x (2 type + 1 hold + 2 delete) = 10 steps returns to the start.
    const frames = run(WORDS, 11);
    expect(frames[10].text).toBe('a');
    expect(frames[10].phase).toBe('typing');
  });

  it('uses the configured delay for each phase', () => {
    const frames = run(WORDS, 5);
    expect(frames[0].delay).toBe(FAST.typeMs); // typing
    expect(frames[1].delay).toBe(FAST.holdMs); // reached full word -> hold
    expect(frames[2].delay).toBe(FAST.deleteMs); // hold -> start deleting
    expect(frames[3].delay).toBe(FAST.deleteMs); // deleting
  });

  it('handles a single word by cycling it on its own', () => {
    const frames = run(['hi'], 6);
    expect(frames.map((f) => f.text)).toEqual(['h', 'hi', 'hi', 'h', '', 'h']);
  });

  it('is a no-op for an empty word list rather than throwing', () => {
    // A mis-configured element should degrade to static, not crash every tick.
    const state = { index: 0, text: '', phase: 'typing' };
    const next = advance(state, []);
    expect(next.state).toBe(state);
    expect(next.delay).toBe(DEFAULT_TIMING.holdMs);
  });

  it('never renders a string that is not a prefix of its word', () => {
    // The property that makes it read as typing rather than as scrambling.
    const words = ['Overheid,', 'Duurzame ondernemers,'];
    let state = { index: 0, text: '', phase: 'typing' };
    for (let i = 0; i < 200; i++) {
      state = advance(state, words, FAST).state;
      expect(words[state.index].startsWith(state.text)).toBe(true);
    }
  });
});
