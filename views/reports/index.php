<p style="color:var(--muted);margin:0 0 22px;max-width:560px">Generate statements and exports for a period. Printable reports open in a new tab — print or save as PDF from there.</p>

<div class="report-grid">

  <div class="report-card">
    <div class="rc-head">
      <div class="rc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg></div>
      <div><div class="rc-title">Income &amp; Expenditure statement</div><div class="rc-desc">Whole-organisation summary for the selected period.</div></div>
    </div>
    <form method="get" action="/reports/org-statement" target="_blank" style="display:contents">
      <div class="rc-fields">
        <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
        <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
      </div>
      <div class="rc-foot"><span class="rc-hint">Printable page</span><button class="btn" type="submit">Open statement</button></div>
    </form>
  </div>

  <div class="report-card">
    <div class="rc-head">
      <div class="rc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg></div>
      <div><div class="rc-title">Excel export</div><div class="rc-desc">All income &amp; expenses, multi-sheet, for your accountant.</div></div>
    </div>
    <form method="get" action="/reports/export" style="display:contents">
      <div class="rc-fields">
        <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
        <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
      </div>
      <div class="rc-foot"><span class="rc-hint">.xlsx download</span><button class="btn" type="submit">Download Excel</button></div>
    </form>
  </div>

  <div class="report-card">
    <div class="rc-head">
      <div class="rc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg></div>
      <div><div class="rc-title">Project / donor statement</div><div class="rc-desc">Opening, received, spent and closing for one grant.</div></div>
    </div>
    <form method="get" action="/reports/statement" target="_blank" style="display:contents">
      <div class="rc-fields">
        <div class="field"><label>Project</label>
          <select name="project_id" required>
            <option value="">—</option>
            <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
        <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
      </div>
      <div class="rc-foot"><span class="rc-hint">Printable page</span><button class="btn" type="submit">Open statement</button></div>
    </form>
  </div>

  <div class="report-card">
    <div class="rc-head">
      <div class="rc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M3 17l5-4 4 3 3-2 6 5"/></svg></div>
      <div><div class="rc-title">Activity report</div><div class="rc-desc">Activities with photos, descriptions and linked costs.</div></div>
    </div>
    <form method="get" action="/reports/activity-report" target="_blank" style="display:contents">
      <div class="rc-fields">
        <div class="field"><label>Project (optional)</label>
          <select name="project_id">
            <option value="">All</option>
            <?php foreach ($projects as $p): ?><option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
        <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
      </div>
      <div class="rc-foot"><span class="rc-hint">Printable page</span><button class="btn" type="submit">Open report</button></div>
    </form>
  </div>

  <?php if (\App\Auth::is('admin')): ?>
  <div class="report-card">
    <div class="rc-head">
      <div class="rc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5v14"/><path d="M4 7h6a4 4 0 0 1 4 4 4 4 0 0 0 4 4h2"/><path d="M4 17h6a4 4 0 0 0 4-4"/><path d="M18 8l2-1-2-1"/><path d="M18 17l2-1-2-1"/></svg></div>
      <div><div class="rc-title">Money flow (Sankey)</div><div class="rc-desc">Visual flow: income sources → accounts → expenses. Admin only.</div></div>
    </div>
    <form method="get" action="/reports/sankey" target="_blank" style="display:contents">
      <div class="rc-fields">
        <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($date_from) ?>"></div>
        <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($date_to) ?>"></div>
      </div>
      <div class="rc-foot"><span class="rc-hint">Opens in a new tab</span><button class="btn" type="submit">Open Sankey</button></div>
    </form>
  </div>
  <?php endif; ?>

</div>
