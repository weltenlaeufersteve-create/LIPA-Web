<?php
$kpiMoney = static function ($v): string {
    $s = number_format((float)$v, 2);
    [$int, $dec] = explode('.', $s);
    return '<span class="cur">TZS</span>' . $int . '<span class="dec">.' . $dec . '</span>';
};
$spentPct = $income > 0 ? min(100, round($expense / $income * 100, 1)) : 0;
$maxCat = 0.0;
foreach ($expenseByCategory as $r) { $maxCat = max($maxCat, (float)$r['total']); }
$walletIco = '<span class="acct-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/></svg></span>';
?>
<form class="filterbar" method="get" action="/">
  <div class="field"><label for="d-from">From</label><input id="d-from" type="date" name="date_from" value="<?= e($f['date_from']) ?>"></div>
  <div class="field"><label for="d-to">To</label><input id="d-to" type="date" name="date_to" value="<?= e($f['date_to']) ?>"></div>
  <div class="actions"><button class="btn" type="submit">Apply</button></div>
</form>

<div class="kpis">
  <div class="card kpi">
    <div class="kpi-label"><span class="dot" style="background:var(--pos)"></span>Income</div>
    <div class="kpi-value num"><?= $kpiMoney($income) ?></div>
    <div class="kpi-tag">Received this period</div>
  </div>
  <div class="card kpi">
    <div class="kpi-label"><span class="dot" style="background:var(--neg)"></span>Expenses</div>
    <div class="kpi-value num"><?= $kpiMoney($expense) ?></div>
    <div class="kpi-tag">Spent this period</div>
  </div>
  <div class="card kpi hero">
    <div class="kpi-label">Balance</div>
    <div class="kpi-value num"><?= $kpiMoney($balance) ?></div>
    <div class="kpi-tag">Income − expenses for the period</div>
  </div>
</div>

<div class="flow">
  <div class="flow-head">
    <span class="flow-title">Where this period's income went</span>
    <span class="flow-sub"><b><?= $spentPct ?>%</b> spent · <b><?= round(100 - $spentPct, 1) ?>%</b> retained</span>
  </div>
  <div class="flow-track" role="img" aria-label="<?= $spentPct ?> percent spent">
    <span class="flow-spent" style="width:<?= $spentPct ?>%"></span>
  </div>
  <div class="flow-legend">
    <span><span class="dot" style="background:var(--neg)"></span>Spent · TZS <?= number_format($expense, 2) ?></span>
    <span><span class="dot" style="background:color-mix(in srgb,var(--pos) 45%,transparent)"></span>Retained · TZS <?= number_format($balance, 2) ?></span>
  </div>
</div>

<h2 class="section-title">Balances by account</h2>
<div class="card table-card">
  <table class="ledger">
    <thead><tr><th>Account</th><th class="r">Received (TZS)</th><th class="r">Current balance (TZS)</th></tr></thead>
    <tbody>
      <?php foreach ($balances as $b): $rec = $received[$b['id']] ?? 0; ?>
        <tr><td class="name"><?= $walletIco ?><?= e($b['name']) ?></td>
          <td class="r money"><?= $rec > 0 ? number_format($rec, 2) : '<span class="muted-cell">—</span>' ?></td>
          <td class="r money"><?= number_format($b['balance'], 2) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($balances)): ?><tr><td colspan="3" class="muted-cell">No accounts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2 class="section-title">By project</h2>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Project</th><th class="r">Income</th><th class="r">Expenses</th><th class="r">Balance</th></tr></thead>
    <tbody>
      <?php foreach ($projects as $name => $vals): $inc = $vals['income'] ?? 0; $exp = $vals['expense'] ?? 0; ?>
        <tr>
          <td class="name"><?= e($name) ?></td>
          <td class="r money" style="color:var(--pos)"><?= number_format($inc, 2) ?></td>
          <td class="r money" style="color:var(--neg)"><?= number_format($exp, 2) ?></td>
          <td class="r money"><span class="pill num"><?= number_format($inc - $exp, 2) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($projects)): ?><tr><td colspan="4" class="muted-cell">No data for this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<h2 class="section-title">Income by donor</h2>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Donor</th><th class="r">Amount (TZS)</th></tr></thead>
    <tbody>
      <?php foreach ($incomeByDonor as $row): ?>
        <tr>
          <td class="name"><?= e($row['name']) ?></td>
          <td class="r money" style="color:var(--pos)"><?= number_format((float)$row['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($incomeByDonor)): ?><tr><td colspan="2" class="muted-cell">No income for this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<h2 class="section-title">Expenses by category</h2>
<div class="card table-card">
  <table class="ledger">
    <thead><tr><th>Category</th><th style="width:46%">Share</th><th class="r">Amount (TZS)</th></tr></thead>
    <tbody>
      <?php foreach ($expenseByCategory as $row): $t = (float)$row['total']; $w = $maxCat > 0 ? round($t / $maxCat * 100, 1) : 0; ?>
        <tr>
          <td class="name"><?= e($row['name'] ?? '(none)') ?></td>
          <td><div class="cat-bar-wrap"><div class="cat-bar"><span style="width:<?= $w ?>%"></span></div></div></td>
          <td class="r money"><?= number_format($t, 2) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($expenseByCategory)): ?><tr><td colspan="3" class="muted-cell">No expenses for this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
