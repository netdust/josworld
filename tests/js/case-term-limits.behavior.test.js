/**
 * Behaviour of case-term-limits.js in the BLOCK editor (cases are edited there): the edited post never keeps
 * more terms than the client allows — one domain, three diensten, one doelgroep. The server clamps the save
 * either way; this proves the editor-side promise against a fake `wp.data` store that behaves like the real
 * one: `editPost` changes the edited attributes and notifies every subscriber.
 */
import { describe, it, expect, beforeEach } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const SOURCE = readFileSync(
  resolve(__dirname, '../../app/content/mu-plugins/josworld-core/assets/js/case-term-limits.js'),
  'utf8',
);

let post;
let subscribers;
let edits;

function fakeWpData(postType = 'case') {
  return {
    subscribe: (callback) => subscribers.push(callback),
    select: () => ({
      getCurrentPostType: () => postType,
      getEditedPostAttribute: (name) => post[name],
    }),
    dispatch: () => ({
      editPost: (change) => {
        edits.push(change);
        post = { ...post, ...change };
        subscribers.forEach((callback) => callback());
      },
    }),
  };
}

/** What the editor does when an editor ticks a box: the id is appended, subscribers hear about it. */
function tick(taxonomy, id) {
  post = { ...post, [taxonomy]: [...(post[taxonomy] ?? []), id] };
  subscribers.forEach((callback) => callback());
}

function load(postType) {
  subscribers = [];
  edits = [];
  window.wp = { data: fakeWpData(postType) };
  window.josworldTermLimits = { domain: '1', dienst: '3', doelgroep: '1' }; // numbers arrive as strings
  new Function(SOURCE)();
}

describe('case term limits in the block editor', () => {
  beforeEach(() => {
    post = { domain: [], dienst: [], doelgroep: [], post_tag: [] };
    load('case');
  });

  it('ticking a second domain replaces the first', () => {
    tick('domain', 5);
    tick('domain', 6);

    expect(post.domain).toEqual([6]);
  });

  it('the doelgroep is either/or', () => {
    tick('doelgroep', 11);
    tick('doelgroep', 12);

    expect(post.doelgroep).toEqual([12]);
  });

  it('drops the fourth dienst and keeps the first three', () => {
    [1, 2, 3, 4].forEach((id) => tick('dienst', id));

    expect(post.dienst).toEqual([1, 2, 3]);
  });

  it('lets a dienst be swapped: untick one, tick another', () => {
    [1, 2, 3].forEach((id) => tick('dienst', id));
    post = { ...post, dienst: [2, 3] };
    subscribers.forEach((callback) => callback());

    tick('dienst', 4);

    expect(post.dienst).toEqual([2, 3, 4]);
  });

  it('does not edit anything while the post is within its limits', () => {
    tick('dienst', 1);
    tick('domain', 5);

    expect(edits).toEqual([]);
  });

  it('leaves every other taxonomy alone', () => {
    [1, 2, 3, 4].forEach((id) => tick('post_tag', id));

    expect(post.post_tag).toEqual([1, 2, 3, 4]);
  });

  it('does nothing on another post type', () => {
    load('page');

    tick('domain', 5);
    tick('domain', 6);

    expect(post.domain).toEqual([5, 6]);
  });

  it('does nothing without the limits from the server, or outside the block editor', () => {
    subscribers = [];
    delete window.josworldTermLimits;
    new Function(SOURCE)();
    expect(subscribers).toEqual([]);

    window.josworldTermLimits = { domain: '1' };
    delete window.wp;
    expect(() => new Function(SOURCE)()).not.toThrow();
  });
});
