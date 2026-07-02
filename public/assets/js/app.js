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

// Activity Save: submit via XHR with a real progress bar (falls back to a normal
// submit if the browser lacks upload progress). Server logic is unchanged — it still
// receives the identical multipart POST; we just show progress and render its reply.
(function () {
  var form = document.getElementById('activity-form');
  if (!form) return;
  var supported = !!(window.FormData && window.XMLHttpRequest && 'upload' in new XMLHttpRequest());
  if (!supported) return; // native submit fallback

  form.addEventListener('submit', function (e) {
    // Photo "Delete" buttons submit to their own formaction — let those go natively.
    if (e.submitter && e.submitter.hasAttribute('formaction')) { return; }
    e.preventDefault();

    var overlay = document.createElement('div');
    overlay.className = 'upload-overlay';
    overlay.innerHTML = '<div class="upload-box"><div class="t">Saving…</div>' +
      '<div class="upload-bar"><div class="upload-fill"></div></div>' +
      '<div class="upload-pct">0%</div></div>';
    document.body.appendChild(overlay);
    var fill = overlay.querySelector('.upload-fill');
    var pct = overlay.querySelector('.upload-pct');
    var title = overlay.querySelector('.t');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action);
    xhr.upload.onprogress = function (ev) {
      if (!ev.lengthComputable) return;
      var p = Math.round(ev.loaded / ev.total * 100);
      fill.style.width = p + '%';
      pct.textContent = p + '%';
      if (p >= 100) { title.textContent = 'Processing…'; pct.textContent = 'Almost done…'; }
    };
    xhr.onload = function () {
      // Render whatever the server returned — the activities list on success,
      // or the form with an error message if validation failed.
      document.open();
      document.write(xhr.responseText);
      document.close();
    };
    xhr.onerror = function () {
      overlay.remove();
      alert('Upload failed — please check your connection and try again.');
    };
    xhr.send(new FormData(form));
  });
})();

// Activity photos: instant thumbnails, per-photo Remove, and a pre-save size check.
(function () {
  var input = document.getElementById('photo-input');
  if (!input) return;
  var grid = document.getElementById('photo-preview');
  var hint = document.getElementById('photo-hint');
  var save = document.getElementById('activity-save');
  var canRemove = (function () { try { new DataTransfer(); return true; } catch (e) { return false; } })();
  function mb(b) { return (b / 1048576).toFixed(1) + ' MB'; }

  function render() {
    var files = Array.prototype.slice.call(input.files || []);
    var remaining = parseInt(input.dataset.remaining || '5', 10);
    var maxPost = parseInt(input.dataset.maxPost || '0', 10);
    var maxFile = parseInt(input.dataset.maxFile || '0', 10);

    if (grid) {
      grid.innerHTML = '';
      files.forEach(function (f, i) {
        if (!/^image\//.test(f.type)) { return; }
        var wrap = document.createElement('div');
        wrap.className = 'photo-thumb';
        if (i >= remaining) { wrap.style.opacity = '.4'; }
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        wrap.appendChild(img);
        var cap = document.createElement('div');
        cap.className = 'form-hint';
        cap.style.cssText = 'max-width:90px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:4px';
        cap.textContent = f.name;
        wrap.appendChild(cap);
        if (canRemove) {
          var rm = document.createElement('button');
          rm.type = 'button';
          rm.className = 'btn-link-danger';
          rm.textContent = 'Remove';
          rm.setAttribute('data-remove-index', i);
          wrap.appendChild(rm);
        }
        grid.appendChild(wrap);
      });
    }

    var total = 0, overName = null;
    files.forEach(function (f) { total += f.size; if (maxFile && f.size > maxFile && !overName) { overName = f.name; } });
    var warn = null;
    if (overName) { warn = '“' + overName + '” is too large (max ' + mb(maxFile) + ' per photo). Please pick a smaller one.'; }
    else if (maxPost && total > maxPost - 300 * 1024) { warn = 'These photos total ' + mb(total) + ', over the ' + mb(maxPost) + ' upload limit. Remove some or use smaller photos.'; }

    if (hint) {
      if (warn) { hint.textContent = warn; hint.style.color = 'var(--neg)'; }
      else if (!files.length) { hint.textContent = hint.dataset.default; hint.style.color = ''; }
      else {
        var n = files.length, msg = n + ' photo' + (n === 1 ? '' : 's') + ' selected';
        if (n > remaining) { msg += ' — only ' + remaining + ' will be saved (max 5)'; }
        hint.textContent = msg + '. Click Save to upload.';
        hint.style.color = '';
      }
    }
    if (save) { save.disabled = !!warn; save.style.opacity = warn ? '.5' : ''; save.style.cursor = warn ? 'not-allowed' : ''; }
  }

  input.addEventListener('change', render);
  if (grid && canRemove) {
    grid.addEventListener('click', function (e) {
      var b = e.target.closest('[data-remove-index]');
      if (!b) { return; }
      var idx = parseInt(b.getAttribute('data-remove-index'), 10);
      var dt = new DataTransfer();
      Array.prototype.slice.call(input.files).forEach(function (f, i) { if (i !== idx) { dt.items.add(f); } });
      input.files = dt.files; // rebuild the selection without that photo
      render();
    });
  }
})();

// Activity expense picker: type-to-filter visible rows + row highlight on tick.
(function () {
  var s = document.getElementById('expSearch');
  if (s) {
    s.addEventListener('input', function () {
      var q = s.value.trim().toLowerCase(), any = false;
      document.querySelectorAll('#expPicker tbody tr').forEach(function (tr) {
        var hit = (tr.dataset.text || '').indexOf(q) !== -1;
        tr.style.display = hit ? '' : 'none';
        if (hit) any = true;
      });
      var nr = document.getElementById('expNoResults');
      if (nr) nr.style.display = any ? 'none' : 'block';
    });
  }
  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('check')) {
      var tr = e.target.closest('tr');
      if (tr) tr.classList.toggle('checked', e.target.checked);
    }
  });
})();

// Mobile account menu: toggle via the role circle; close on outside click or Esc.
document.addEventListener('click', function (e) {
  var pop = document.querySelector('.acct-pop');
  if (!pop) return;
  if (e.target.closest('[data-acct-toggle]')) { pop.hidden = !pop.hidden; }
  else if (!e.target.closest('.acct-pop')) { pop.hidden = true; }
});

// Help popup: open via the "?" button, close via ✕, backdrop, or Esc.
document.addEventListener('click', function (e) {
  var m = document.getElementById('help-modal');
  if (!m) return;
  if (e.target.closest('#help-toggle')) { m.hidden = false; }
  else if (e.target.closest('[data-help-close]')) { m.hidden = true; }
});
document.addEventListener('keydown', function (e) {
  if (e.key !== 'Escape') return;
  var m = document.getElementById('help-modal');
  if (m && !m.hidden) { m.hidden = true; }
  var pop = document.querySelector('.acct-pop');
  if (pop && !pop.hidden) { pop.hidden = true; }
});

// Confirm dialogs for any form with data-confirm="message"
document.addEventListener('submit', (e) => {
  const msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) { e.preventDefault(); }
});
