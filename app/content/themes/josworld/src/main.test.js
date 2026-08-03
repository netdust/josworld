/**
 * Behavioral tests for the ntdstAPI wrapper (src/main.js).
 *
 * Contract under test (window.ntdstAPI):
 *  - _nonce(action) caches nonces per action (one /get_nonce fetch per action)
 *  - call(action, params) POSTs /wp-json/ntdst/v1/action; on
 *    {success:false, data:{code:'invalid_nonce'}} it clears the cached nonce
 *    and retries with a freshly fetched one
 *  - any other {success:false} rejects with the server message
 *
 * No browser, no WordPress: environment is 'node' with `window` aliased to
 * globalThis (main.js only needs `window` at import time; `document` is only
 * touched inside Alpine component callbacks, which the Alpine stub never runs).
 * `fetch` is a deterministic vi.fn router — responses are consumed in FIFO
 * order per endpoint, so promise ordering cannot vary between runs.
 */
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// main.js is side-effectful at import: stub Alpine so plugin/data/start are
// no-ops. The CSS import is transformed to an empty module by Vitest.
vi.mock('alpinejs', () => ({
  default: { plugin: vi.fn(), data: vi.fn(), start: vi.fn() },
}));
vi.mock('@alpinejs/collapse', () => ({ default: {} }));

// node environment: main.js assigns window.Alpine / window.ntdstAPI at import.
globalThis.window = globalThis;

await import('./main.js');

const NONCE_URL = '/wp-json/ntdst/v1/get_nonce';
const ACTION_URL = '/wp-json/ntdst/v1/action';

/**
 * Deterministic fetch stub: queued responses per endpoint, consumed in order.
 */
function stubFetch({ nonces = [], actionResponses = [] }) {
  const nonceQueue = nonces.map((nonce) => ({ success: true, data: { nonce } }));
  const actionQueue = [...actionResponses];
  const fetchMock = vi.fn(async (url) => {
    const body =
      url === NONCE_URL ? nonceQueue.shift()
      : url === ACTION_URL ? actionQueue.shift()
      : undefined;
    if (body === undefined) throw new Error(`unexpected fetch: ${url}`);
    return { json: async () => body };
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}

const callsTo = (fetchMock, url) =>
  fetchMock.mock.calls.filter(([requestUrl]) => requestUrl === url);

const sentBody = (call) => JSON.parse(call[1].body);

describe('ntdstAPI', () => {
  beforeEach(() => {
    // The wrapper is a singleton on window; reset its per-action nonce cache.
    window.ntdstAPI._nonceCache = {};
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('caches the nonce per action: two calls for the same action fetch the nonce endpoint only once', async () => {
    const fetchMock = stubFetch({
      nonces: ['nonce-1'],
      actionResponses: [
        { success: true, data: { first: true } },
        { success: true, data: { second: true } },
      ],
    });

    const first = await window.ntdstAPI.call('save_thing', { id: 1 });
    const second = await window.ntdstAPI.call('save_thing', { id: 2 });

    expect(first).toEqual({ first: true });
    expect(second).toEqual({ second: true });
    // Contract: the nonce is cached per action — exactly ONE nonce fetch.
    // (Watched RED first with a deliberate expectation of 2; failed with
    // "expected ... length of 2 but got 1", proving the assertion bites.)
    expect(callsTo(fetchMock, NONCE_URL)).toHaveLength(1);
    // Both action requests must carry the same cached nonce.
    const actionCalls = callsTo(fetchMock, ACTION_URL);
    expect(actionCalls).toHaveLength(2);
    expect(sentBody(actionCalls[0]).nonce).toBe('nonce-1');
    expect(sentBody(actionCalls[1]).nonce).toBe('nonce-1');
  });

  it('on invalid_nonce it fetches a fresh nonce, retries, and resolves with the success data', async () => {
    // Second attempt succeeds — the happy retry path. The both-attempts-fail
    // denial path is covered by the bounded-retry test below. (The FIFO stub
    // throws on queue exhaustion, so even an unbounded retry loop rejects
    // deterministically — it cannot hang.)
    const fetchMock = stubFetch({
      nonces: ['stale-nonce', 'fresh-nonce'],
      actionResponses: [
        { success: false, data: { code: 'invalid_nonce' } },
        { success: true, data: { saved: 'yes' } },
      ],
    });

    const result = await window.ntdstAPI.call('save_thing', { id: 7 });

    expect(result).toEqual({ saved: 'yes' });
    // A FRESH nonce was fetched between the two attempts...
    expect(callsTo(fetchMock, NONCE_URL)).toHaveLength(2);
    // ...and the retry actually used it.
    const actionCalls = callsTo(fetchMock, ACTION_URL);
    expect(actionCalls).toHaveLength(2);
    expect(sentBody(actionCalls[0]).nonce).toBe('stale-nonce');
    expect(sentBody(actionCalls[1]).nonce).toBe('fresh-nonce');
  });

  it('retries invalid_nonce exactly once: two failing attempts reject with no further fetches', async () => {
    // Nonce invalid on BOTH attempts — the retry must be bounded. Hang-safe:
    // the FIFO stub throws 'unexpected fetch' the moment either queue is
    // exhausted, so an unbounded retry loop rejects instead of hanging.
    const fetchMock = stubFetch({
      nonces: ['n1', 'n2'],
      actionResponses: [
        { success: false, data: { code: 'invalid_nonce' } },
        { success: false, data: { code: 'invalid_nonce' } },
      ],
    });

    await expect(window.ntdstAPI.call('save_thing', { id: 3 })).rejects.toThrow();

    // Bounded retry contract: EXACTLY 2 action attempts and 2 nonce fetches.
    expect(callsTo(fetchMock, ACTION_URL)).toHaveLength(2);
    expect(callsTo(fetchMock, NONCE_URL)).toHaveLength(2);
  });

  it('rejects with the server message on a non-invalid_nonce failure, without retrying', async () => {
    const fetchMock = stubFetch({
      nonces: ['nonce-1'],
      actionResponses: [
        { success: false, data: { code: 'forbidden', message: 'Geen toegang' } },
      ],
    });

    await expect(window.ntdstAPI.call('save_thing')).rejects.toThrow('Geen toegang');
    expect(callsTo(fetchMock, ACTION_URL)).toHaveLength(1);
    expect(callsTo(fetchMock, NONCE_URL)).toHaveLength(1);
  });

  it('rejects with the server message when the nonce endpoint itself fails, caching nothing', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn(async () => ({
        json: async () => ({ success: false, data: { message: 'Token geweigerd' } }),
      }))
    );

    await expect(window.ntdstAPI.call('save_thing')).rejects.toThrow('Token geweigerd');
    expect(window.ntdstAPI._nonceCache).toEqual({});
  });
});
