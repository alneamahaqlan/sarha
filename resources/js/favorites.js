// AJAX heart toggle for "save" (services/offers) + "favourite" (clinics).
//
// Progressive enhancement: every target is a real <form method="POST"> that
// still works without JS (full submit -> controller back()). When JS is on we
// intercept the submit, POST in the background, and flip the button's visual
// state in place — so tapping the heart on the homepage no longer reloads the
// whole page. The matching forms are marked with `.js-save-form` and their
// button carries `data-fav-toggle` + the state metadata below.

function applyState(btn, saved) {
  const on = (btn.dataset.classOn || '').split(' ').filter(Boolean);
  const off = (btn.dataset.classOff || '').split(' ').filter(Boolean);
  (saved ? off : on).forEach((c) => btn.classList.remove(c));
  (saved ? on : off).forEach((c) => btn.classList.add(c));

  const form = btn.closest('form');
  form?.querySelector('[data-fav-icon-on]')?.classList.toggle('hidden', !saved);
  form?.querySelector('[data-fav-icon-off]')?.classList.toggle('hidden', saved);

  const title = saved ? btn.dataset.titleOn : btn.dataset.titleOff;
  if (title) {
    btn.title = title;
    btn.setAttribute('aria-label', title);
  }
  btn.dataset.saved = saved ? '1' : '0';
}

document.addEventListener('submit', function (e) {
  const form = e.target.closest('form.js-save-form');
  if (!form) return;

  e.preventDefault();
  const btn = form.querySelector('[data-fav-toggle]');
  if (btn && btn.dataset.busy) return; // ignore rapid double taps
  if (btn) btn.dataset.busy = '1';

  fetch(form.action, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
    body: new FormData(form),
    credentials: 'same-origin',
  })
    .then((res) => {
      // Session lapsed / CSRF rotated — a reload re-establishes both.
      if (res.status === 401 || res.status === 419) {
        window.location.reload();
        return null;
      }
      if (!res.ok) throw new Error('save toggle failed');
      return res.json();
    })
    .then((data) => {
      if (data && btn) applyState(btn, !!data.saved);
    })
    .catch(() => {
      // Network/server error: fall back to the plain submit so the action
      // still goes through (this reloads, but only on genuine failure).
      form.submit();
    })
    .finally(() => {
      if (btn) delete btn.dataset.busy;
    });
});
