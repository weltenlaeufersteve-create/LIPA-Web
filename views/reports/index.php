<h1>Reports</h1>
<p>Export all income and expenses for a period to Excel (multiple sheets).</p>
<form method="get" action="/reports/export" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($date_from) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($date_to) ?>"></label>
  <button class="btn btn-primary" type="submit">Download Excel</button>
</form>
