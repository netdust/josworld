/**
 * Josworld — the JOS logo's eye follows the cursor.
 *
 * The logo mark (images/jos-logo.svg) is an exported animation whose "O" is an
 * eye: a white ellipse whose `ry` is driven 106 → 16 → 106 by SMIL (the BLINK),
 * and a separate pupil subtree wrapped in `<g mask="url(#…)">` so the pupil is
 * clipped to the eye no matter where it sits.
 *
 * That split is what makes this cheap: we never touch the blink. We only add a
 * translate to the pupil, and the existing mask guarantees it can never escape
 * the eye. The pupil's own SMIL drift also stays — it keeps the mark alive when
 * the cursor is idle — because we translate a WRAPPER we insert rather than the
 * animated group itself. (Writing `transform` on an element SMIL is animating
 * would be ignored: the animation overrides the attribute's base value.)
 *
 * Requires the SVG to be INLINE in the page. YOOtheme does that when
 * `config.logo.image_svg_inline` is true — it adds `uk-svg`, and UIkit hides the
 * <img> and injects an <svg> next to it.
 */

const SVGNS = 'http://www.w3.org/2000/svg';

/**
 * How far the pupil travels, as a fraction of the geometric slack between the
 * eye's radius and the pupil's. Below 1 so the pupil never kisses the rim.
 */
const TRAVEL_FRACTION = 0.72;

/**
 * Cursor distance (px) at which deflection saturates — beyond this it's full.
 *
 * Deliberately SMALL. A real eye aims at its target by direction, not distance:
 * something 10cm away gets as much rotation as something across the room. The
 * ease-in only exists to stop the pupil snapping when the cursor crosses over
 * the mark itself. It also has to be small because the logo sits in the top-left
 * corner — with a large radius the cursor can never get far enough left or above
 * it to saturate, and the eye visibly under-reacts on exactly those two sides.
 */
const SATURATION_RADIUS = 90;

/** Per-frame easing toward the target. Lower = lazier, more eye-like. */
const EASING = 0.18;

/** Below this movement (user units) the settle loop stops. */
const SETTLE_EPSILON = 0.05;

/**
 * Where the pupil should sit, given the cursor offset from the eye centre.
 *
 * Direction is normalised, then scaled by an ELLIPTICAL travel budget, so the
 * reachable locus matches the eye's own shape (wider than it is tall) instead
 * of a circle that would clip at the top and bottom.
 *
 * @param {number} dx       cursor x minus eye-centre x, in px
 * @param {number} dy       cursor y minus eye-centre y, in px
 * @param {number} travelX  max horizontal excursion
 * @param {number} travelY  max vertical excursion
 * @param {number} radius   distance at which deflection reaches maximum
 * @returns {{x: number, y: number}}
 */
export function eyeOffset(dx, dy, travelX, travelY, radius = SATURATION_RADIUS) {
  const distance = Math.hypot(dx, dy);

  // Cursor exactly on the eye centre: no direction to point at.
  if (distance === 0) {
    return { x: 0, y: 0 };
  }

  // Saturate rather than grow without bound — a real eye is already fully
  // deflected long before the target is far away.
  const reach = radius > 0 ? Math.min(1, distance / radius) : 1;

  return {
    x: (dx / distance) * travelX * reach,
    y: (dy / distance) * travelY * reach,
  };
}

/**
 * Make every id in an inlined SVG unique, rewriting internal references.
 *
 * The exported logo hard-codes ids i0…i9/m2. The header renders the mark TWICE
 * (mobile + desktop), so inlining both puts duplicate ids in one document and
 * the desktop copy's `mask="url(#i1)"` silently resolves to the mobile copy's
 * mask. It renders today only because the two are identical — this removes the
 * coincidence.
 *
 * @param {SVGElement} svg
 * @param {string} suffix
 */
export function namespaceSvgIds(svg, suffix) {
  const remap = new Map();

  svg.querySelectorAll('[id]').forEach((el) => {
    const oldId = el.getAttribute('id');
    const newId = `${oldId}-${suffix}`;
    remap.set(oldId, newId);
    el.setAttribute('id', newId);
  });

  if (remap.size === 0) {
    return;
  }

  // Rewrite url(#id) in any attribute (mask, filter, fill, clip-path, …).
  svg.querySelectorAll('*').forEach((el) => {
    Array.from(el.attributes).forEach((attr) => {
      if (attr.value.indexOf('url(#') === -1) {
        return;
      }
      const next = attr.value.replace(/url\(#([^)]+)\)/g, (whole, id) =>
        remap.has(id) ? `url(#${remap.get(id)})` : whole,
      );
      if (next !== attr.value) {
        el.setAttribute(attr.name, next);
      }
    });
  });
}

/**
 * Uniform scale factor of an element's user space in screen pixels.
 * @param {DOMMatrix|null} ctm
 */
function ctmScale(ctm) {
  return ctm ? Math.hypot(ctm.a, ctm.b) || 1 : 1;
}

/**
 * Prepare one inlined logo SVG. Returns a per-frame updater, or null when the
 * SVG isn't the eye mark (no masked pupil subtree / no eye ellipse).
 */
function prepareEye(svg, index) {
  namespaceSvgIds(svg, `jw${index}`);

  // The pupil lives under the masked group; the mask is what clips it.
  const masked = svg.querySelector('g[mask]');
  // The eye itself is the ellipse OUTSIDE <defs>/<mask> (the copies inside are
  // the mask's own geometry and have no screen CTM).
  const eye = Array.from(svg.querySelectorAll('ellipse')).find(
    (el) => !el.closest('mask') && !el.closest('defs'),
  );

  if (!masked || !eye) {
    return null;
  }

  // Retire the pupil's built-in WANDER, keep everything else.
  //
  // Measured on the real mark: the exported animation swings the pupil ±38 user
  // units, against a cursor-travel budget of ~±18. Left running it does not read
  // as "alive under a follow behaviour" — it overwhelms the tracking, drags the
  // pupil away from the cursor, and makes the rest position unmeasurable (its
  // bbox breathes between 109 and 125 units wide).
  //
  // Only the drift goes. The BLINK is untouched: that is an <animate> on the
  // ellipse's rx/ry, living outside this subtree. The first animateTransform in
  // here is the whole mark's shared ±2° sway, which every other top-level group
  // also carries — dropping it would desync the pupil from the letters, so it
  // stays and only the inner drift pair is removed.
  Array.from(masked.querySelectorAll('animateTransform'))
    .slice(1)
    .forEach((node) => node.remove());

  // Translate a wrapper we own; the shared sway above still composes with it.
  const pupil = document.createElementNS(SVGNS, 'g');
  pupil.setAttribute('data-jw-eye', '');
  while (masked.firstChild) {
    pupil.appendChild(masked.firstChild);
  }
  masked.appendChild(pupil);

  let currentX = 0;
  let currentY = 0;
  // The pupil's natural position is NOT the eye's centre (the artwork parks it
  // off to one side). Measured once, then subtracted, so the travel budget is
  // centred on the EYE — otherwise full deflection overshoots the ring on one
  // side and undershoots on the other.
  let restOffsetX = null;
  let restOffsetY = 0;

  return function update(pointerX, pointerY) {
    const ctm = eye.getScreenCTM();
    // Hidden header (the other breakpoint's copy) — nothing to position.
    if (!ctm) {
      return false;
    }

    const centre = new DOMPoint(eye.cx.baseVal.value, eye.cy.baseVal.value).matrixTransform(ctm);

    // baseVal, not animVal: during a blink `ry` collapses to 16, and using the
    // live value would make vertical travel collapse with it.
    //
    // The eye is a STROKED ellipse (the dark ring of the "O"), so its rx/ry is
    // the stroke's CENTRELINE. The opening the pupil may move inside ends half a
    // stroke further in — using rx directly lets the pupil slide under the ring
    // and merge with it at the extremes.
    const halfStroke = (parseFloat(getComputedStyle(eye).strokeWidth) || 0) / 2;
    const eyeRx = Math.max(0, eye.rx.baseVal.value - halfStroke);
    const eyeRy = Math.max(0, eye.ry.baseVal.value - halfStroke);

    const box = pupil.getBBox();
    const travelX = Math.max(0, eyeRx - box.width / 2) * TRAVEL_FRACTION;
    const travelY = Math.max(0, eyeRy - box.height / 2) * TRAVEL_FRACTION;

    // Measure the resting offset once, on the first frame, while our own
    // transform is still identity. getBBox is geometry-only, so the mask does
    // not distort it the way getBoundingClientRect would.
    if (restOffsetX === null) {
      const pupilCtm = pupil.getScreenCTM();
      const pupilCentre = new DOMPoint(
        box.x + box.width / 2,
        box.y + box.height / 2,
      ).matrixTransform(pupilCtm);
      const userScale = ctmScale(pupilCtm);
      restOffsetX = (pupilCentre.x - centre.x) / userScale;
      restOffsetY = (pupilCentre.y - centre.y) / userScale;
    }

    // Cursor delta is in screen px; the travel budget is in user units, so the
    // saturation radius has to be compared in screen px too.
    const aim = eyeOffset(
      pointerX - centre.x,
      pointerY - centre.y,
      travelX,
      travelY,
      SATURATION_RADIUS,
    );

    // `aim` is where the pupil should sit relative to the EYE centre; the
    // transform has to close the gap from where it rests.
    const target = { x: aim.x - restOffsetX, y: aim.y - restOffsetY };

    currentX += (target.x - currentX) * EASING;
    currentY += (target.y - currentY) * EASING;

    pupil.setAttribute('transform', `translate(${currentX.toFixed(2)} ${currentY.toFixed(2)})`);

    const settled =
      Math.abs(target.x - currentX) < SETTLE_EPSILON &&
      Math.abs(target.y - currentY) < SETTLE_EPSILON;

    return !settled;
  };
}

/**
 * Wire up every logo on the page. Safe to call before UIkit has inlined the
 * SVGs — it observes for them.
 *
 * Callable in a non-browser context (main.js is imported under Vitest's `node`
 * environment, where `window` is aliased to globalThis and there is no
 * `document`): it no-ops rather than throwing, so importing the bundle stays
 * side-effect-safe.
 *
 * @param {Document|HTMLElement} [root]
 */
export function initLogoEye(root) {
  const scope = root || (typeof document !== 'undefined' ? document : null);
  if (!scope || typeof window === 'undefined' || typeof MutationObserver === 'undefined') {
    return;
  }

  const host = scope instanceof Document ? scope.body : scope;
  if (!host) {
    return;
  }

  // Someone who asked the OS for less motion did not ask for a tracking eye.
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }
  // No hover pointer (touch) — there is no cursor to follow.
  if (window.matchMedia && !window.matchMedia('(hover: hover)').matches) {
    return;
  }

  const updaters = [];
  const seen = new WeakSet();
  let pointerX = 0;
  let pointerY = 0;
  let running = false;

  function frame() {
    let more = false;
    for (const update of updaters) {
      if (update(pointerX, pointerY)) {
        more = true;
      }
    }
    if (more) {
      requestAnimationFrame(frame);
    } else {
      running = false;
    }
  }

  function kick() {
    if (!running && updaters.length) {
      running = true;
      requestAnimationFrame(frame);
    }
  }

  function attach() {
    scope.querySelectorAll('.uk-logo svg').forEach((svg, i) => {
      if (seen.has(svg)) {
        return;
      }
      seen.add(svg);
      const update = prepareEye(svg, updaters.length || i);
      if (update) {
        updaters.push(update);
      }
    });
  }

  attach();

  // UIkit swaps <img uk-svg> for inline <svg> asynchronously, so the SVG may
  // not exist yet at DOMContentLoaded. Watch until it lands.
  const observer = new MutationObserver(() => {
    attach();
    if (updaters.length) {
      kick();
    }
  });
  observer.observe(host, { childList: true, subtree: true });

  window.addEventListener(
    'pointermove',
    (event) => {
      pointerX = event.clientX;
      pointerY = event.clientY;
      kick();
    },
    { passive: true },
  );

  // The eye's screen position changes on scroll/resize even when the cursor
  // doesn't move — re-run so it keeps aiming at the same place.
  window.addEventListener('scroll', kick, { passive: true });
  window.addEventListener('resize', kick, { passive: true });
}
