<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Organisation Statement — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:32px;max-width:900px;}
  h1{font-size:1.4rem;margin:0 0 2px;} h2{font-size:1.1rem;margin:20px 0 4px;} h3{font-size:1rem;margin:18px 0 4px;}
  .muted{color:#555;font-size:.9rem;}
  table{width:100%;border-collapse:collapse;margin:8px 0;}
  th,td{border-bottom:1px solid #ddd;padding:6px 8px;text-align:left;font-size:.88rem;}
  th{background:#f0f0f0;}
  .num{text-align:right;}
  .summary{display:flex;gap:12px;flex-wrap:wrap;margin:14px 0;}
  .summary div{border:1px solid #ddd;border-radius:8px;padding:10px 14px;min-width:150px;font-size:.85rem;}
  .summary strong{display:block;font-size:1.15rem;margin-top:2px;}
  .actions{margin:0 0 18px;}
  .btn{padding:8px 14px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5;cursor:pointer;text-decoration:none;color:#111;font-size:.9rem;}
  @media print { .actions{display:none;} body{padding:0;} }
</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn" href="/reports">Back</a>
</div>

<h1><?= e($s['org_name'] ?? 'Organisation') ?></h1>
<?php if (!empty($s['org_address'])): ?><div class="muted"><?= nl2br(e($s['org_address'])) ?></div><?php endif; ?>
<?php if (!empty($s['org_email'])): ?><div class="muted"><?= e($s['org_email']) ?></div><?php endif; ?>
<?php if (!empty($s['tax_id']) || !empty($s['ngo_number'])): ?>
  <div class="muted">
    <?php if (!empty($s['tax_id'])): ?>Tax ID: <?= e($s['tax_id']) ?><?php endif; ?>
    <?php if (!empty($s['tax_id']) && !empty($s['ngo_number'])): ?> &middot; <?php endif; ?>
    <?php if (!empty($s['ngo_number'])): ?>Reg. No: <?= e($s['ngo_number']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<h2>Income &amp; Expenditure Statement</h2>
<p class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</p>

<div class="summary">
  <div>Opening balance<strong><?= number_format($d['opening'], 2) ?></strong></div>
  <div>Total income<strong><?= number_format($d['income'], 2) ?></strong></div>
  <div>Total expenses<strong><?= number_format($d['expenses'], 2) ?></strong></div>
  <div>Net (surplus/deficit)<strong><?= number_format($d['net'], 2) ?></strong></div>
  <div>Closing balance<strong><?= number_format($d['closing'], 2) ?></strong></div>
</div>

<h3>Income by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['income_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['income_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenses by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>By project</h3>
<table>
  <thead><tr><th>Project</th><th class="num">Income (TZS)</th><th class="num">Expenses (TZS)</th><th class="num">Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['by_project'] as $r): ?>
    <tr><td><?= e($r['name']) ?></td>
      <td class="num"><?= number_format($r['income'], 2) ?></td>
      <td class="num"><?= number_format($r['expense'], 2) ?></td>
      <td class="num"><?= number_format($r['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['by_project'])): ?><tr><td colspan="4">No data for this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Balances by account (as at <?= e($d['to']) ?>)</h3>
<table>
  <thead><tr><th>Account</th><th class="num">Balance (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['balances'] as $r): ?>
    <tr><td><?= e($r['name']) ?></td><td class="num"><?= number_format($r['balance'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['balances'])): ?><tr><td colspan="2">No accounts.</td></tr><?php endif; ?>
  </tbody>
</table>

<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
