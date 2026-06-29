<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Project Statement — <?= e($d['project']['name']) ?></title>
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

<h2>Project Statement</h2>
<p><strong><?= e($d['project']['name']) ?></strong><br>
<span class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</span></p>

<div class="summary">
  <div>Opening balance<strong><?= number_format($d['opening'], 2) ?></strong></div>
  <div>Funds received<strong><?= number_format($d['received'], 2) ?></strong></div>
  <div>Expenditure<strong><?= number_format($d['spent'], 2) ?></strong></div>
  <div>Closing balance<strong><?= number_format($d['closing'], 2) ?></strong></div>
</div>

<h3>Funds received</h3>
<table>
  <thead><tr><th>Date</th><th>Donor</th><th>Description</th><th>Original</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['income_lines'] as $r): ?>
    <tr>
      <td><?= e($r['date']) ?></td>
      <td><?= e($r['contact_name'] ?? '') ?></td>
      <td><?= e($r['description'] ?? '') ?></td>
      <td><?= ($r['currency'] !== 'TZS') ? e($r['currency']) . ' ' . number_format((float)$r['amount_original'], 2) : '' ?></td>
      <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($d['income_lines'])): ?><tr><td colspan="5">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenditure by category</h3>
<table>
  <thead><tr><th>Category</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_by_category'] as $r): ?>
    <tr><td><?= e($r['name'] ?? '(none)') ?></td><td class="num"><?= number_format((float)$r['total'], 2) ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_by_category'])): ?><tr><td colspan="2">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<h3>Expenditure detail</h3>
<table>
  <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Description</th><th class="num">Amount (TZS)</th></tr></thead>
  <tbody>
  <?php foreach ($d['expense_lines'] as $r): ?>
    <tr>
      <td><?= e($r['date']) ?></td>
      <td><?= e($r['contact_name'] ?? '') ?></td>
      <td><?= e($r['category_name'] ?? '') ?></td>
      <td><?= e($r['description'] ?? '') ?></td>
      <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($d['expense_lines'])): ?><tr><td colspan="5">None in this period.</td></tr><?php endif; ?>
  </tbody>
</table>

<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
