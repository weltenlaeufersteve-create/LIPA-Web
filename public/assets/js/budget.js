// Budget scenario page: collapsible product cards, per-product materials→cost,
// dynamic rows, and a LIVE PREVIEW mirror of the calculation.
// CANONICAL calculation is src/Budget/ScenarioCalc.php (unit cost = Σ materials ÷ batch_yield,
// cached on save); this mirror is preview-only — the server value wins on Save and print.
(function () {
  var form = document.getElementById('budget-form');
  if (!form) return;

  var f0 = function (n) { return Math.round(n).toLocaleString('en-US'); };
  var num = function (el) { if (!el) return 0; var v = parseFloat(String(el.value).replace(/[^\d.-]/g, '')); return isNaN(v) ? 0 : v; };
  var setText = function (id, t) { var el = document.getElementById(id); if (el) el.textContent = t; };
  var all = function (root, sel) { return Array.prototype.slice.call(root.querySelectorAll(sel)); };
  var rowsOf = function (tblId) { return all(document, '#' + tblId + ' tbody tr.brow'); };

  function recompute() {
    var totRev = { low: 0, mid: 0, high: 0 }, totVar = { low: 0, mid: 0, high: 0 }, totUnits = { low: 0, mid: 0, high: 0 };

    all(document, '#products .bcard').forEach(function (card) {
      var batch = 0;
      all(card, '.bmat tbody [name^="p_mat_amount"]').forEach(function (inp) { batch += num(inp); });
      var yield_ = Math.max(num(card.querySelector('[name="p_yield[]"]')), 1);
      var unitCost = batch / yield_;
      var price = num(card.querySelector('[name="p_price[]"]'));
      var margin = price - unitCost;
      var u = { low: num(card.querySelector('[name="p_low[]"]')), mid: num(card.querySelector('[name="p_mid[]"]')), high: num(card.querySelector('[name="p_high[]"]')) };

      var bt = card.querySelector('[data-batch-total]'); if (bt) bt.textContent = f0(batch);
      var uc = card.querySelector('[data-unit-cost]'); if (uc) uc.textContent = f0(unitCost);
      var mg = card.querySelector('[data-margin]'); if (mg) { mg.textContent = f0(margin); mg.style.color = margin <= 0 ? 'var(--neg)' : ''; }
      var cb = card.querySelector('[data-contrib]'); if (cb) cb.textContent = f0(u.mid * margin);

      var nameInp = card.querySelector('[name="p_name[]"]');
      var title = card.querySelector('[data-title]'); if (title) title.textContent = (nameInp && nameInp.value.trim()) || 'New product';
      var unitInp = card.querySelector('[name="p_unit[]"]'); var unitLabel = (unitInp && unitInp.value.trim()) || 'unit';
      all(card, '[data-unit-label]').forEach(function (s) { s.textContent = unitLabel; });
      var sum = card.querySelector('[data-sum]'); if (sum) sum.textContent = price ? (f0(margin) + ' / ' + unitLabel + ' margin') : '';

      ['low', 'mid', 'high'].forEach(function (k) { totRev[k] += u[k] * price; totVar[k] += u[k] * unitCost; totUnits[k] += u[k]; });
    });

    var oneTime = 0; rowsOf('tbl-onetime').forEach(function (tr) { oneTime += num(tr.querySelector('[name="ot_amount[]"]')); });
    var fixed = 0;   rowsOf('tbl-fixed').forEach(function (tr) { fixed += num(tr.querySelector('[name="mf_amount[]"]')); });
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
    var rp = document.getElementById('r-profit'); if (rp) rp.textContent = (midProfit < 0 ? '−' : '') + f0(Math.abs(midProfit));
    setText('r-breakeven', midProfit > 0 ? (net / midProfit).toFixed(1) + ' mo' : '—');

    var remaining = Math.max(midProfit, 0), hasAlloc = false;
    rowsOf('tbl-alloc').forEach(function (tr) {
      var amt = num(tr.querySelector('[name="al_amount[]"]'));
      var nameEl = tr.querySelector('[name="al_name[]"]');
      if (amt > 0 || (nameEl && nameEl.value.trim())) hasAlloc = true;
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
  }

  function cloneCleared(node) {
    var c = node.cloneNode(true);
    all(c, 'input').forEach(function (i) { i.value = ''; });
    all(c, '[data-batch-total],[data-unit-cost],[data-margin],[data-contrib],[data-alloc-pct]').forEach(function (x) { x.textContent = '—'; });
    var bar = c.querySelector('[data-alloc-bar]'); if (bar) bar.style.width = '0%';
    return c;
  }

  document.addEventListener('click', function (e) {
    // add a product card
    if (e.target.closest('[data-add-product]')) {
      var box = document.getElementById('products');
      var cards = box.querySelectorAll('.bcard');
      var clone = cloneCleared(cards[cards.length - 1]);
      clone.classList.remove('collapsed');
      // keep a single blank material row in the clone
      var body = clone.querySelector('.bmat tbody');
      var mrows = body.querySelectorAll('tr.bmrow');
      for (var i = 1; i < mrows.length; i++) mrows[i].remove();
      box.appendChild(clone);
      recompute();
      return;
    }
    // add a material row within a product card
    if (e.target.closest('[data-add-material]')) {
      var tbody = e.target.closest('.bcard').querySelector('.bmat tbody');
      var mr = tbody.querySelectorAll('tr.bmrow');
      tbody.appendChild(cloneCleared(mr[mr.length - 1]));
      recompute();
      return;
    }
    // add a row to a standalone table (start-up / fixed / allocations)
    if (e.target.closest('[data-add-row]')) {
      var tblId = e.target.closest('[data-add-row]').getAttribute('data-add-row');
      var body = document.querySelector('#' + tblId + ' tbody');
      if (body) {
        var brows = body.querySelectorAll('tr.brow');
        body.appendChild(cloneCleared(brows[brows.length - 1]));
        recompute();
      }
      return;
    }
    // remove a whole product card
    if (e.target.closest('[data-card-remove]')) {
      var card = e.target.closest('.bcard');
      var box2 = document.getElementById('products');
      if (box2.querySelectorAll('.bcard').length > 1) { card.remove(); }
      else { all(card, 'input').forEach(function (i) { i.value = ''; }); }
      recompute();
      return;
    }
    // remove a table row (materials / start-up / fixed / allocations)
    if (e.target.closest('[data-row-remove]')) {
      var tr = e.target.closest('tr'), tb = tr.parentNode;
      if (tb.querySelectorAll('tr').length > 1) { tr.remove(); }
      else { all(tr, 'input').forEach(function (i) { i.value = ''; }); }
      recompute();
      return;
    }
    // collapse / expand a product card (clicking its header, but not the remove button)
    var head = e.target.closest('.bcard-head');
    if (head) { head.parentNode.classList.toggle('collapsed'); }
  });

  // number formatting: commas on blur, raw while editing
  function fmtField(el) { var v = parseFloat(String(el.value).replace(/[^\d.-]/g, '')); el.value = isNaN(v) ? '' : v.toLocaleString('en-US', { maximumFractionDigits: 2 }); }
  document.addEventListener('focus', function (e) { if (e.target.classList && e.target.classList.contains('bnum')) e.target.value = e.target.value.replace(/,/g, ''); }, true);
  document.addEventListener('blur', function (e) { if (e.target.classList && e.target.classList.contains('bnum')) fmtField(e.target); }, true);

  // on submit, renumber each product's material field indices to match card order
  form.addEventListener('submit', function () {
    all(document, '#products .bcard').forEach(function (card, i) {
      all(card, '.bmat [name^="p_mat_name"]').forEach(function (el) { el.name = 'p_mat_name[' + i + '][]'; });
      all(card, '.bmat [name^="p_mat_amount"]').forEach(function (el) { el.name = 'p_mat_amount[' + i + '][]'; });
    });
  });

  form.addEventListener('input', recompute);
  all(document, '#budget-form .bnum').forEach(function (el) { if (el.value !== '') fmtField(el); });
  recompute();
})();
