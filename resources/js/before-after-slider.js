// Interactive before/after comparison slider (progressive enhancement).
//
// Enhances every [data-ba-slider]: the visitor drags a vertical divider to
// wipe between the "before" image (clipped, on top) and the "after" image
// (the base layer). Works with mouse, touch and keyboard, and is RTL-aware —
// on the Arabic (RTL) site the inline-start edge is the RIGHT edge, so the
// reveal grows from the right. No external libraries.

function setupSlider(slider) {
  if (slider.dataset.baReady) return; // idempotent — safe to re-init
  slider.dataset.baReady = '1';

  const before = slider.querySelector('.ba-slider__img--before');
  const divider = slider.querySelector('[data-ba-divider]');
  const handle = slider.querySelector('[data-ba-handle]');
  if (!before || !divider) return;

  const rtl = getComputedStyle(slider).direction === 'rtl';
  let pos = 50; // percent of the "before" image revealed from the inline-start edge

  function apply() {
    // Clip the before image so only `pos`% from the inline-start edge shows.
    const hidden = (100 - pos).toFixed(2) + '%';
    before.style.clipPath = rtl
      ? 'inset(0 0 0 ' + hidden + ')'  // RTL: inline-start is the right edge
      : 'inset(0 ' + hidden + ' 0 0)';
    divider.style.insetInlineStart = pos + '%';
    if (handle) handle.setAttribute('aria-valuenow', String(Math.round(pos)));
  }

  function posFromClientX(clientX) {
    const rect = slider.getBoundingClientRect();
    if (!rect.width) return pos;
    const fromStart = rtl ? rect.right - clientX : clientX - rect.left;
    return Math.max(0, Math.min(100, (fromStart / rect.width) * 100));
  }

  let dragging = false;

  function start(e) {
    dragging = true;
    slider.classList.add('is-dragging');
    move(e);
    if (handle) {
      try { handle.focus({ preventScroll: true }); } catch (_) { handle.focus(); }
    }
  }
  function move(e) {
    if (!dragging) return;
    const clientX = e.touches ? (e.touches[0] && e.touches[0].clientX) : e.clientX;
    if (clientX == null) return;
    if (e.cancelable) e.preventDefault(); // suppress horizontal scroll / text selection
    pos = posFromClientX(clientX);
    apply();
  }
  function end() {
    if (!dragging) return;
    dragging = false;
    slider.classList.remove('is-dragging');
  }

  // Pointer events unify mouse + touch where supported; fall back otherwise.
  if (window.PointerEvent) {
    slider.addEventListener('pointerdown', start);
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', end);
  } else {
    slider.addEventListener('mousedown', start);
    window.addEventListener('mousemove', move);
    window.addEventListener('mouseup', end);
    slider.addEventListener('touchstart', start, { passive: false });
    slider.addEventListener('touchmove', move, { passive: false });
    slider.addEventListener('touchend', end);
    slider.addEventListener('touchcancel', end);
  }

  // Keyboard: arrows nudge the divider in the intuitive visual direction,
  // accounting for RTL (where moving "right" reduces the inline-start offset).
  if (handle) {
    handle.addEventListener('keydown', function (e) {
      const step = e.shiftKey ? 10 : 2;
      let next = null;
      switch (e.key) {
        case 'ArrowRight': next = pos + (rtl ? -step : step); break;
        case 'ArrowLeft':  next = pos + (rtl ? step : -step); break;
        case 'ArrowUp':    next = pos + step; break;
        case 'ArrowDown':  next = pos - step; break;
        case 'Home':       next = 0; break;
        case 'End':        next = 100; break;
        default: return;
      }
      e.preventDefault();
      pos = Math.max(0, Math.min(100, next));
      apply();
    });
  }

  apply();
}

export function initBeforeAfterSliders(root) {
  (root || document).querySelectorAll('[data-ba-slider]').forEach(setupSlider);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', function () { initBeforeAfterSliders(); });
} else {
  initBeforeAfterSliders();
}
