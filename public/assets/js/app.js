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
