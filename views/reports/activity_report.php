<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Activity Report — <?= e($s['org_name'] ?? 'Organisation') ?></title>
<style>
  body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;padding:32px;max-width:900px;}
  h1{font-size:1.4rem;margin:0 0 2px;} h2{font-size:1.1rem;margin:20px 0 4px;} h3{font-size:1rem;margin:16px 0 2px;}
  .muted{color:#555;font-size:.9rem;}
  table{width:100%;border-collapse:collapse;margin:6px 0;}
  th,td{border-bottom:1px solid #ddd;padding:5px 8px;text-align:left;font-size:.86rem;}
  th{background:#f0f0f0;}
  .num{text-align:right;}
  .actions{margin:0 0 18px;}
  .btn{padding:8px 14px;border:1px solid #ccc;border-radius:6px;background:#f5f5f5;cursor:pointer;text-decoration:none;color:#111;font-size:.9rem;}
  .activity{border-top:2px solid #333;padding-top:10px;margin-top:18px;}
  .photos{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0;}
  .photos img{max-height:150px;border:1px solid #ccc;border-radius:4px;}
  @page { margin: 14mm; }
  @media print {
    .actions{display:none;} body{padding:0;max-width:none;}
    thead{display:table-header-group;}
    tr{break-inside:avoid;page-break-inside:avoid;}
    h2,h3{break-after:avoid;page-break-after:avoid;}
    .activity{break-inside:avoid;page-break-inside:avoid;}
  }
</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn" href="/reports">Back</a>
</div>

<h1><?= e($s['org_name'] ?? 'Organisation') ?></h1>
<?php if (!empty($s['tax_id']) || !empty($s['ngo_number'])): ?>
  <div class="muted">
    <?php if (!empty($s['tax_id'])): ?>Tax ID: <?= e($s['tax_id']) ?><?php endif; ?>
    <?php if (!empty($s['tax_id']) && !empty($s['ngo_number'])): ?> &middot; <?php endif; ?>
    <?php if (!empty($s['ngo_number'])): ?>No.: <?= e($s['ngo_number']) ?><?php endif; ?>
  </div>
<?php endif; ?>

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

<p style="border-top:2px solid #333;padding-top:8px;margin-top:18px" class="num"><strong>Grand total of activity costs (TZS): <?= number_format($d['grand_total'], 2) ?></strong></p>
<p class="muted">Generated <?= date('Y-m-d H:i') ?> &middot; LIPA</p>
</body>
</html>
