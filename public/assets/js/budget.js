// Budget scenario page: dynamic rows + a LIVE PREVIEW mirror of the calculation.
// CANONICAL calculation is src/Budget/ScenarioCalc.php — this mirror is preview-only;
// the server value wins on Save and on the print page.
(function () {
  var form = document.getElementById('budget-form');
  if (!form) return;

  var f0 = function (n) { return Math.round(n).toLocaleString('en-US'); };
  var num = function (el) { if (!el) return 0; var v = parseFloat(String(el.value).replace(/[^\d.-]/g, '')); return isNaN(v) ? 0 : v; };
  var setText = function (id, t) { var el = document.getElementById(id); if (el) el.textContent = t; };
  var rowsOf = function (tblId) { return Array.prototype.slice.call(document.querySelectorAll('#' + tblId + ' tbody tr.brow')); };
  var q = function (tr, name) { return tr.querySelector('[name="' + name + '"]'); };

  function recompute() {
    var totRev = { low: 0, mid: 0, high: 0 }, totVar = { low: 0, mid: 0, high: 0 }, totUnits = { low: 0, mid: 0, high: 0 };

    rowsOf('tbl-products').forEach(function (tr) {
      var price = num(q(tr, 'p_price[]')), cost = num(q(tr, 'p_cost[]')), margin = price - cost;
      var u = { low: num(q(tr, 'p_low[]')), mid: num(q(tr, 'p_mid[]')), high: num(q(tr, 'p_high[]')) };
      var mc = tr.querySelector('[data-margin]');
      if (mc) { mc.textContent = f0(margin); mc.style.color = margin <= 0 ? 'var(--neg)' : ''; }
      var cc = tr.querySelector('[data-contrib]');
      if (cc) cc.textContent = f0(u.mid * margin);
      ['low', 'mid', 'high'].forEach(function (k) { totRev[k] += u[k] * price; totVar[k] += u[k] * cost; totUnits[k] += u[k]; });
    });

    var oneTime = 0; rowsOf('tbl-onetime').forEach(function (tr) { oneTime += num(q(tr, 'ot_amount[]')); });
    var fixed = 0;   rowsOf('tbl-fixed').forEach(function (tr) { fixed += num(q(tr, 'mf_amount[]')); });
    var funded = num(form.querySelector('[name="funded_amount"]'));
    var net = Math.max(oneTime - funded, 0);
    setText('r-onetime', f0(oneTime));
    setText('r-netstartup', f0(net));
    setText('r-fixed', f0(fixed));

    var midProfit = 0;
    ['low', 'mid', 'high'].forEach(function (k) {
      var profit = totRev[k] - totVar[k] - fixed;
      if (k === 'mid') midProfit = profit;
      setText('c-' + k + '-units', f0(totUnits[k]));
      setText('c-' + k + '-rev', f0(totRev[k]));
      setText('c-' + k + '-var', '−' + f0(totVar[k]));
      setText('c-' + k + '-fixed', '−' + f0(fixed));
      var pc = document.getElementById('c-' + k + '-profit');
      if (pc) { pc.textContent = (profit < 0 ? '−' : '') + f0(Math.abs(profit)); pc.style.color = profit > 0 ? 'var(--pos)' : profit < 0 ? 'var(--neg)' : ''; }
      setText('c-' + k + '-be', profit > 0 ? (net / profit).toFixed(1) + ' mo' : '—');
    });

    setText('r-revenue', f0(totRev.mid));
    var rp = document.getElementById('r-profit');
    if (rp) { rp.textContent = (midProfit < 0 ? '−' : '') + f0(Math.abs(midProfit)); }
    setText('r-breakeven', midProfit > 0 ? (net / midProfit).toFixed(1) + ' mo' : '—');

    // allocation waterfall on the realistic (mid) profit
    var remaining = Math.max(midProfit, 0), hasAlloc = false;
    rowsOf('tbl-alloc').forEach(function (tr) {
      var amt = num(q(tr, 'al_amount[]'));
      if (amt > 0 || (q(tr, 'al_name[]') && q(tr, 'al_name[]').value.trim())) hasAlloc = true;
      var cov = amt > 0 ? Math.min(remaining / amt, 1) : 0;
      remaining = Math.max(remaining - amt, 0);
      var bar = tr.querySelector('[data-alloc-bar]'); if (bar) bar.style.width = (cov * 100) + '%';
      var pct = tr.querySelector('[data-alloc-pct]'); if (pct) pct.textContent = Math.round(cov * 100) + '%';
    });
    var note = document.getElementById('r-allocnote');
    if (note) {
      if (!hasAlloc) note.textContent = '';
      else if (midProfit <= 0) note.textContent = 'No profit to allocate in the realistic case.';
      else if (remaining > 0) note.textContent = 'All covered — ' + f0(remaining) + ' TZS/month left for reserves.';
      else note.textContent = 'Profit does not fully cover the allocations at the realistic volume.';
    }

    var bt = num(document.getElementById('bh-total')), by = num(document.getElementById('bh-yield'));
    setText('bh-result', by > 0 ? f0(bt / by) : '—');
  }

  // add / remove dynamic rows
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add-row]');
    if (add) {
      var body = document.querySelector('#' + add.getAttribute('data-add-row') + ' tbody');
      var rows = body.querySelectorAll('tr.brow');
      var clone = rows[rows.length - 1].cloneNode(true);
      clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
      clone.querySelectorAll('[data-margin],[data-contrib]').forEach(function (c) { c.textContent = '—'; });
      var bar = clone.querySelector('[data-alloc-bar]'); if (bar) bar.style.width = '0%';
      var pct = clone.querySelector('[data-alloc-pct]'); if (pct) pct.textContent = '—';
      body.appendChild(clone);
      recompute();
      return;
    }
    var rm = e.target.closest('[data-row-remove]');
    if (rm) {
      var tr = rm.closest('tr.brow'), body = tr.parentNode;
      if (body.querySelectorAll('tr.brow').length > 1) { tr.remove(); }
      else { tr.querySelectorAll('input').forEach(function (i) { i.value = ''; }); }
      recompute();
    }
  });

  // thousands-separators on the numeric fields: formatted on blur, raw while editing
  function fmtField(el) {
    var v = parseFloat(String(el.value).replace(/[^\d.-]/g, ''));
    el.value = isNaN(v) ? '' : v.toLocaleString('en-US', { maximumFractionDigits: 2 });
  }
  document.addEventListener('focus', function (e) {
    if (e.target.classList && e.target.classList.contains('bnum')) { e.target.value = e.target.value.replace(/,/g, ''); }
  }, true);
  document.addEventListener('blur', function (e) {
    if (e.target.classList && e.target.classList.contains('bnum')) { fmtField(e.target); }
  }, true);

  form.addEventListener('input', recompute);
  // format the initial server-rendered values, then compute
  Array.prototype.forEach.call(document.querySelectorAll('#budget-form .bnum'), function (el) { if (el.value !== '') fmtField(el); });
  recompute();
})();
