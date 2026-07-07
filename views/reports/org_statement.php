<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Organisation Statement — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<link rel="stylesheet" href="<?= asset('/assets/css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/css/print.css') ?>">
<style>:root{--accent: <?= e(\App\hex_color($s['accent_color'] ?? null)) ?>;}</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn ghost" href="/reports">Back</a>
</div>

<div class="doc-head">
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
</div>

<h2>Income &amp; Expenditure Statement</h2>
<p class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</p>

<div class="summary">
  <div>Opening balance<strong><?= number_format($d['opening'], 2) ?></strong></div>
  <div>Total income<strong class="pos"><?= number_format($d['income'], 2) ?></strong></div>
  <div>Total expenses<strong class="neg"><?= number_format($d['expenses'], 2) ?></strong></div>
  <div>Net (surplus/deficit)<strong><?= number_format($d['net'], 2) ?></strong></div>
  <div class="hero">Closing balance<strong><?= number_format($d['closing'], 2) ?></strong></div>
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

<?php if (!empty($d['receipt_images'])): ?>
<section class="receipt-appendix">
  <h3>Appendix — receipt photos</h3>
  <div class="receipt-grid">
    <?php foreach ($d['receipt_images'] as $r): ?>
      <figure class="receipt-fig">
        <figcaption><?= e($r['date']) ?> &middot; <?= e($r['contact_name'] ?? '') ?> &middot; <?= e($r['category_name'] ?? '') ?> &middot; <?= number_format((float)$r['amount_tzs'], 2) ?> TZS</figcaption>
        <img src="/expenses/<?= (int)$r['id'] ?>/receipt" alt="Receipt <?= e($r['date']) ?>">
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($d['receipt_pdfs'])): ?>
<section class="receipt-pdf-list">
  <h3>Appendix — PDF receipts on file</h3>
  <table>
    <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th class="num">Amount (TZS)</th><th>Receipt</th></tr></thead>
    <tbody>
    <?php foreach ($d['receipt_pdfs'] as $r): ?>
      <tr>
        <td><?= e($r['date']) ?></td>
        <td><?= e($r['contact_name'] ?? '') ?></td>
        <td><?= e($r['category_name'] ?? '') ?></td>
        <td class="num"><?= number_format((float)$r['amount_tzs'], 2) ?></td>
        <td><a href="/expenses/<?= (int)$r['id'] ?>/receipt">view</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
