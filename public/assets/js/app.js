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
  if (b) {
    b.textContent = t === 'dark' ? '☀' : '🌙';
    b.title = t === 'dark' ? 'Switch to light mode' : 'Switch to dark mode';
  }
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

// Click a column header to sort a .data-table (client-side). Numbers sort
// numerically (commas stripped); everything else (incl. YYYY-MM-DD dates) sorts as text.
document.addEventListener('click', function (e) {
  var th = e.target.closest('th');
  if (!th) return;
  var table = th.closest('table.data-table');
  if (!table || !table.tBodies[0]) return;
  var headerCells = Array.prototype.slice.call(th.parentNode.children);
  var idx = headerCells.indexOf(th);
  if (idx < 0 || th.textContent.trim() === '') return; // skip the actions column
  var tbody = table.tBodies[0];
  var rows = Array.prototype.slice.call(tbody.rows).filter(function (r) { return r.cells.length === headerCells.length; });
  if (rows.length < 2) return;

  var asc = th.getAttribute('data-sort') !== 'asc';
  headerCells.forEach(function (h) { if (h !== th) h.removeAttribute('data-sort'); });
  th.setAttribute('data-sort', asc ? 'asc' : 'desc');

  var num = function (v) {
    var s = String(v).replace(/,/g, '').trim();
    return /^-?\d+(\.\d+)?$/.test(s) ? parseFloat(s) : null;
  };
  rows.sort(function (a, b) {
    var x = a.cells[idx].textContent.trim(), y = b.cells[idx].textContent.trim();
    var nx = num(x), ny = num(y), cmp;
    if (nx !== null && ny !== null) cmp = nx - ny;
    else cmp = x.localeCompare(y);
    return asc ? cmp : -cmp;
  });
  rows.forEach(function (r) { tbody.appendChild(r); });
});

// Accent-colour picker (Settings): live-preview + preset swatches.
document.addEventListener('input', function (e) {
  if (e.target && e.target.id === 'accentInput') {
    document.documentElement.style.setProperty('--accent', e.target.value);
    var hex = document.getElementById('accentHex');
    if (hex) hex.textContent = e.target.value;
    document.querySelectorAll('.accent-picker .sw').forEach(function (s) {
      s.setAttribute('aria-pressed', s.dataset.accent && s.dataset.accent.toLowerCase() === e.target.value.toLowerCase() ? 'true' : 'false');
    });
  }
});
document.addEventListener('click', function (e) {
  var sw = e.target.closest('.accent-picker .sw');
  if (!sw || !sw.dataset.accent) return;
  var input = document.getElementById('accentInput');
  if (input) { input.value = sw.dataset.accent; input.dispatchEvent(new Event('input', { bubbles: true })); }
});

// Confirm dialogs for any form with data-confirm="message"
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { e.preventDefault(); }
});
