<h1>Dashboard</h1>
<form method="get" action="/" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn" type="submit">Apply</button>
</form>

<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Income (TZS)</span><strong style="font-size:1.4rem"><?= number_format($income, 2) ?></strong>
  </div>
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Expenses (TZS)</span><strong style="font-size:1.4rem"><?= number_format($expense, 2) ?></strong>
  </div>
  <div class="btn" style="flex:1;min-width:180px;flex-direction:column;align-items:flex-start;cursor:default">
    <span>Balance (TZS)</span><strong style="font-size:1.4rem"><?= number_format($balance, 2) ?></strong>
  </div>
</div>

<h2>Balances by account</h2>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Account</th><th>Current balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($balances as $b): ?>
    <tr><td><?= e($b['name']) ?></td><td><?= number_format($b['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($balances)): ?><tr><td colspan="2">No accounts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h2>By project</h2>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Project</th><th>Income (TZS)</th><th>Expenses (TZS)</th><th>Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($projects as $name => $vals): $inc = $vals['income'] ?? 0; $exp = $vals['expense'] ?? 0; ?>
    <tr>
      <td><?= e($name) ?></td>
      <td><?= number_format($inc, 2) ?></td>
      <td><?= number_format($exp, 2) ?></td>
      <td><?= number_format($inc - $exp, 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($projects)): ?><tr><td colspan="4">No data for this period.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>

<h2>Recent activity</h2>
<ul>
  <?php foreach ($activity as $a): ?>
    <li><?= e($a['created_at']) ?> — <?= e($a['user_name'] ?? 'system') ?>: <?= e($a['description'] ?? ($a['action'] . ' ' . $a['entity_type'])) ?></li>
  <?php endforeach; ?>
  <?php if (empty($activity)): ?><li>No activity yet.</li><?php endif; ?>
</ul>
