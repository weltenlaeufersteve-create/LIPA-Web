// Minimal UI helpers shared across pages.
function showToast(message, type = 'success') {
  let c = document.getElementById('toast-container');
  if (!c) { c = document.createElement('div'); c.id = 'toast-container'; document.body.appendChild(c); }
  const t = document.createElement('div');
  t.className = 'toast toast-' + type;
  t.textContent = message;
  c.appendChild(t);
  setTimeout(() => t.remove(), 2800);
}

// Theme toggle: flip light/dark, persist per-browser in localStorage.
function applyTheme(t) {
  document.documentElement.setAttribute('data-theme', t);
  var b = document.getElementById('theme-toggle');
  if (b) { b.textContent = t === 'dark' ? '☀ Light mode' : '🌙 Dark mode'; }
}
document.addEventListener('DOMContentLoaded', function () {
  var t = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  applyTheme(t);
});
document.addEventListener('click', function (e) {
  if (!e.target.closest('#theme-toggle')) return;
  var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
  var next = cur === 'dark' ? 'light' : 'dark';
  try { localStorage.setItem('lipa_theme', next); } catch (err) {}
  applyTheme(next);
});

// Mobile nav: toggle .nav-open on the shell via hamburger / scrim.
document.addEventListener('click', (e) => {
  const shell = document.querySelector('.app-shell');
  if (!shell) return;
  if (e.target.closest('[data-nav-toggle]')) { shell.classList.toggle('nav-open'); }
  else if (e.target.classList.contains('scrim')) { shell.classList.remove('nav-open'); }
});

// Confirm dialogs for any form with data-confirm="message"
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { e.preventDefault(); }
});
