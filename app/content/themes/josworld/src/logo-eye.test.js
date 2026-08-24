/**
 * Behavioural tests for the logo eye's geometry (src/logo-eye.js).
 *
 * Only `eyeOffset` is covered here — it is the part carrying real logic
 * (normalise → saturate → elliptical budget). The DOM half (namespaceSvgIds,
 * prepareEye) needs a real SVG layout engine with getScreenCTM/getBBox, which
 * jsdom does not implement; that half is verified against the live page in a
 * real browser instead.
 *
 * The load-bearing property is the LAST test: the pupil must never travel
 * further than its budget, because the budget is what keeps it inside the eye.
 */
import { describe, it, expect } from 'vitest';
import { eyeOffset } from './logo-eye.js';

const TRAVEL_X = 30;
const TRAVEL_Y = 20;
const RADIUS = 90;

describe('eyeOffset', () => {
  it('does not move when the cursor is exactly on the eye centre', () => {
    // Guards a divide-by-zero: normalising a zero-length vector is NaN, which
    // would serialise into the transform and blank the pupil.
    expect(eyeOffset(0, 0, TRAVEL_X, TRAVEL_Y, RADIUS)).toEqual({ x: 0, y: 0 });
  });

  it('looks fully right when the cursor is far to the right', () => {
    const { x, y } = eyeOffset(1000, 0, TRAVEL_X, TRAVEL_Y, RADIUS);
    expect(x).toBeCloseTo(TRAVEL_X, 6);
    expect(y).toBeCloseTo(0, 6);
  });

  it('looks fully left when the cursor is far to the left', () => {
    const { x } = eyeOffset(-1000, 0, TRAVEL_X, TRAVEL_Y, RADIUS);
    expect(x).toBeCloseTo(-TRAVEL_X, 6);
  });

  it('uses the smaller vertical budget so the locus is elliptical, not round', () => {
    const { x, y } = eyeOffset(0, 1000, TRAVEL_X, TRAVEL_Y, RADIUS);
    expect(y).toBeCloseTo(TRAVEL_Y, 6);
    expect(x).toBeCloseTo(0, 6);
    // The whole point: vertical travel is NOT the horizontal one.
    expect(TRAVEL_Y).toBeLessThan(TRAVEL_X);
  });

  it('deflects proportionally while the cursor is inside the saturation radius', () => {
    const { x } = eyeOffset(RADIUS / 2, 0, TRAVEL_X, TRAVEL_Y, RADIUS);
    expect(x).toBeCloseTo(TRAVEL_X / 2, 6);
  });

  it('saturates instead of growing without bound past the radius', () => {
    const near = eyeOffset(RADIUS, 0, TRAVEL_X, TRAVEL_Y, RADIUS);
    const far = eyeOffset(RADIUS * 40, 0, TRAVEL_X, TRAVEL_Y, RADIUS);
    expect(far.x).toBeCloseTo(near.x, 6);
  });

  it('never exceeds the travel budget, for any direction or distance', () => {
    // If this ever fails the pupil escapes the eye's mask on some angle.
    for (let deg = 0; deg < 360; deg += 7) {
      const rad = (deg * Math.PI) / 180;
      for (const dist of [1, 12, 130, 259, 260, 261, 5000]) {
        const { x, y } = eyeOffset(
          Math.cos(rad) * dist,
          Math.sin(rad) * dist,
          TRAVEL_X,
          TRAVEL_Y,
          RADIUS,
        );
        expect(Math.abs(x)).toBeLessThanOrEqual(TRAVEL_X + 1e-9);
        expect(Math.abs(y)).toBeLessThanOrEqual(TRAVEL_Y + 1e-9);
        // On the ellipse or inside it — never outside.
        const onEllipse = (x / TRAVEL_X) ** 2 + (y / TRAVEL_Y) ** 2;
        expect(onEllipse).toBeLessThanOrEqual(1 + 1e-9);
        expect(Number.isFinite(x) && Number.isFinite(y)).toBe(true);
      }
    }
  });

  it('treats a zero saturation radius as fully deflected rather than dividing by zero', () => {
    const { x } = eyeOffset(5, 0, TRAVEL_X, TRAVEL_Y, 0);
    expect(x).toBeCloseTo(TRAVEL_X, 6);
  });
});
