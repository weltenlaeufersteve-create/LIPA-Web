<h1>Reports</h1>
<p>Export all income and expenses for a period to Excel (multiple sheets).</p>
<form method="get" action="/reports/export" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Download Excel</button>
</form>

<h2 style="margin-top:28px">Project statement</h2>
<p>A printable statement for one project/grant over a period (opens in a new tab → Print → Save as PDF).</p>
<form method="get" action="/reports/statement" target="_blank" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">Project
    <select name="project_id" required>
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Open statement</button>
</form>

<h2 style="margin-top:28px">Organisation statement</h2>
<p>A printable whole-organisation Income &amp; Expenditure statement for a period (opens in a new tab → Print → Save as PDF).</p>
<form method="get" action="/reports/org-statement" target="_blank" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Open statement</button>
</form>
