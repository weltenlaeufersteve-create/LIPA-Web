<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Report — <?= e($s['org_name'] ?? 'Organisation') ?></title>
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
  <?php if (!empty($s['tax_id']) || !empty($s['ngo_number'])): ?>
    <div class="muted">
      <?php if (!empty($s['tax_id'])): ?>Tax ID: <?= e($s['tax_id']) ?><?php endif; ?>
      <?php if (!empty($s['tax_id']) && !empty($s['ngo_number'])): ?> &middot; <?php endif; ?>
      <?php if (!empty($s['ngo_number'])): ?>No.: <?= e($s['ngo_number']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<h2>Activity Report</h2>
<p class="muted">Period: <?= e($d['from']) ?> to <?= e($d['to']) ?> &middot; Currency: TZS</p>

<?php foreach ($d['activities'] as $item): $a = $item['activity']; ?>
  <div class="activity">
    <h3><?= e($a['date']) ?> — <?= e($a['title']) ?><?php if (!empty($a['project_name'])): ?> <span class="muted">(<?= e($a['project_name']) ?>)</span><?php endif; ?></h3>
    <?php if (!empty($a['description'])): ?><p><?= nl2br(e($a['description'])) ?></p><?php endif; ?>
    <?php if (!empty($item['photos'])): ?>
      <div class="photos">
        <?php foreach ($item['photos'] as $ph): ?>
          <img src="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>" alt="">
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($item['expenses'])): ?>
      <table>
        <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Description</th><th class="num">Amount (TZS)</th></tr></thead>
        <tbody>
        <?php foreach ($item['expenses'] as $ex): ?>
          <tr><td><?= e($ex['date']) ?></td><td><?= e($ex['contact_name'] ?? '') ?></td>
            <td><?= e($ex['category_name'] ?? '') ?></td><td><?= e($ex['description'] ?? '') ?></td>
            <td class="num"><?= number_format((float)$ex['amount_tzs'], 2) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <p class="num"><strong>Activity cost (TZS): <?= number_format($item['cost'], 2) ?></strong></p>
  </div>
<?php endforeach; ?>
<?php if (empty($d['activities'])): ?><p>No activities in this period.</p><?php endif; ?>

<p class="grand"><strong>Grand total of activity costs (TZS): <?= number_format($d['grand_total'], 2) ?></strong></p>
<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
