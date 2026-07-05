<?php
$f0 = static fn($v) => number_format((float)$v, 0);
$oneTimeItems = array_values(array_filter($items, fn($i) => $i['item_type'] === 'one_time'));
$fixedItems   = array_values(array_filter($items, fn($i) => $i['item_type'] === 'monthly_fixed'));
$mid = $calc['cases']['mid'];
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Budget scenario — <?= e($s['name']) ?></title>
<link rel="stylesheet" href="<?= asset('/assets/css/theme.css') ?>">
<link rel="stylesheet" href="<?= asset('/assets/css/print.css') ?>">
<style>:root{--accent: <?= e(\App\hex_color($set['accent_color'] ?? null)) ?>;}
  .cases .mid{ background:var(--accent-quiet); }
  .cases thead .mid{ color:var(--accent); }
  .summary{ display:grid; grid-template-columns:repeat(3,1fr); }
  .summary div{ min-width:0; }
</style>
</head>
<body>
<div class="actions">
  <button class="btn" onclick="window.print()">Print / Save as PDF</button>
  <a class="btn ghost" href="/budget/<?= (int)$s['id'] ?>">Back</a>
</div>

<div class="doc-head">
  <h1><?= e($set['org_name'] ?? 'Organisation') ?></h1>
  <?php if (!empty($set['org_address'])): ?><div class="muted"><?= nl2br(e($set['org_address'])) ?></div><?php endif; ?>
  <?php if (!empty($set['tax_id']) || !empty($set['ngo_number'])): ?>
    <div class="muted">
      <?php if (!empty($set['tax_id'])): ?>Tax ID: <?= e($set['tax_id']) ?><?php endif; ?>
      <?php if (!empty($set['tax_id']) && !empty($set['ngo_number'])): ?> &middot; <?php endif; ?>
      <?php if (!empty($set['ngo_number'])): ?>No.: <?= e($set['ngo_number']) ?><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<h2>Budget scenario</h2>
<p><strong><?= e($s['name']) ?></strong> <span class="muted">(<?= e(ucfirst($s['status'])) ?>)</span><br>
<span class="muted">
  <?php if (!empty($s['description'])): ?><?= e($s['description']) ?> &middot; <?php endif; ?>
  <?php if (!empty($s['project_name'])): ?>Linked project: <?= e($s['project_name']) ?> &middot; <?php endif; ?>
  Currency: TZS</span></p>

<h3>Products</h3>
<table>
  <thead><tr><th>Product</th><th class="r">Price</th><th class="r">Cost</th><th class="r">Margin</th><th class="r">Units/mo (realistic)</th><th class="r">Contribution/mo</th></tr></thead>
  <tbody>
    <?php foreach ($calc['products'] as $p): ?>
      <tr>
        <td><?= e($p['name']) ?> <span class="muted">/ <?= e($p['unit_name']) ?></span></td>
        <td class="r num"><?= $f0($p['sale_price']) ?></td>
        <td class="r num"><?= $f0($p['unit_cost']) ?></td>
        <td class="r num <?= $p['margin_negative'] ? 'neg' : '' ?>"><?= $f0($p['margin']) ?></td>
        <td class="r num"><?= $f0($p['units']['mid']) ?></td>
        <td class="r num"><?= $f0($p['contribution']['mid']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($calc['products'])): ?><tr><td colspan="6" class="muted">No products.</td></tr><?php endif; ?>
  </tbody>
</table>

<?php $withMaterials = array_filter($products, fn($p) => !empty($p['materials'])); ?>
<?php if (!empty($withMaterials)): ?>
<h3>How each unit cost is worked out</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:4px">
  <?php foreach ($products as $p): if (empty($p['materials'])) continue; $bt = 0; ?>
    <div>
      <div style="font-weight:700;margin-bottom:4px"><?= e($p['name']) ?></div>
      <table class="t num">
        <?php foreach ($p['materials'] as $m): $bt += (float)$m['amount']; ?>
          <tr><td><?= e($m['name']) ?></td><td class="r"><?= $f0($m['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td>Batch total</td><td class="r"><?= $f0($bt) ?></td></tr>
        <tr><td class="muted">÷ <?= (int)$p['batch_yield'] ?> per batch</td><td class="r"></td></tr>
        <tr><td><strong>= Cost / <?= e($p['unit_name']) ?></strong></td><td class="r" style="color:var(--accent);font-weight:700"><?= $f0($p['unit_cost']) ?></td></tr>
      </table>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-top:8px">
  <div>
    <h3>Start-up costs</h3>
    <table>
      <tbody>
        <?php foreach ($oneTimeItems as $it): ?>
          <tr><td><?= e($it['name']) ?></td><td class="r num"><?= $f0($it['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td>Total start-up</td><td class="r num"><?= $f0($calc['one_time_total']) ?></td></tr>
        <tr><td class="muted">− Funded by partner</td><td class="r num"><?= $f0($s['funded_amount']) ?></td></tr>
        <tr><td><strong>= NGO share to recover</strong></td><td class="r num" style="color:var(--accent);font-weight:700"><?= $f0($calc['net_startup']) ?></td></tr>
      </tbody>
    </table>
  </div>
  <div>
    <h3>Fixed costs / month</h3>
    <table>
      <tbody>
        <?php foreach ($fixedItems as $it): ?>
          <tr><td><?= e($it['name']) ?></td><td class="r num"><?= $f0($it['amount']) ?></td></tr>
        <?php endforeach; ?>
        <tr class="total-row"><td>Total / month</td><td class="r num"><?= $f0($calc['fixed_total']) ?></td></tr>
      </tbody>
    </table>
  </div>
</div>

<h3>The three cases — per month</h3>
<table class="cases">
  <thead><tr><th>Per month</th><th class="r">Pessimistic</th><th class="r mid">Realistic</th><th class="r">Optimistic</th></tr></thead>
  <tbody>
    <?php
    $labels = ['units_total'=>'Units sold','revenue'=>'Revenue','variable'=>'Variable costs','fixed'=>'Fixed costs','profit'=>'Profit'];
    foreach ($labels as $key => $label):
    ?>
      <tr>
        <td class="<?= $key === 'profit' ? '' : 'muted' ?>"><?= $key === 'profit' ? '<strong>Profit</strong>' : $label ?></td>
        <?php foreach (['low','mid','high'] as $c): $v = $calc['cases'][$c][$key];
          $disp = in_array($key, ['variable','fixed'], true) ? '−' . $f0($v) : $f0($v);
          $cls = $c === 'mid' ? 'mid ' : '';
          if ($key === 'profit') { $cls .= $v > 0 ? 'pos' : ($v < 0 ? 'neg' : ''); if ($v < 0) $disp = '−' . $f0(abs($v)); }
        ?>
          <td class="r num <?= $cls ?>"><?= $disp ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    <tr>
      <td class="muted">Break-even (NGO share)</td>
      <?php foreach (['low','mid','high'] as $c): $be = $calc['cases'][$c]['break_even']; ?>
        <td class="r num <?= $c === 'mid' ? 'mid' : '' ?>"><?= $be !== null ? number_format($be, 1) . ' mo' : '—' ?></td>
      <?php endforeach; ?>
    </tr>
  </tbody>
</table>

<h3>Result — realistic case</h3>
<div class="summary">
  <div>Revenue / mo (realistic)<strong><?= $f0($mid['revenue']) ?></strong></div>
  <div>Break-even<strong><?= $mid['break_even'] !== null ? number_format($mid['break_even'], 1) . ' mo' : '—' ?></strong></div>
  <div class="hero">Monthly profit (realistic)<strong><?= $f0($mid['profit']) ?></strong></div>
</div>
<?php if ($mid['break_even'] !== null && (float)$s['funded_amount'] > 0): ?>
  <p class="muted">Break-even on the NGO's own share (<?= $f0($calc['net_startup']) ?> TZS). Without partner funding the full start-up would recover in <?= number_format($mid['break_even_unfunded'], 1) ?> months.</p>
<?php endif; ?>

<?php if (!empty($calc['allocations'])): ?>
<h3>What the profit pays for — realistic case</h3>
<?php foreach ($calc['allocations'] as $a): ?>
  <div style="display:grid;grid-template-columns:180px 1fr 52px;gap:12px;align-items:center;padding:7px 0;border-bottom:1px solid var(--line-soft)">
    <div><?= e($a['name']) ?><br><span class="muted"><?= $f0($a['monthly_amount']) ?> TZS/mo</span></div>
    <div class="cat-bar" style="height:11px;border:1px solid var(--accent-line);background:var(--surface-2)"><span style="display:block;height:100%;width:<?= (int)$a['coverage_pct'] ?>%;background:var(--accent)"></span></div>
    <div class="r num" style="font-weight:700"><?= (int)$a['coverage_pct'] ?>%</div>
  </div>
<?php endforeach; ?>
<?php if (!empty($calc['alloc_note'])): ?><p class="muted" style="margin-top:8px;font-style:italic"><?= e($calc['alloc_note']) ?></p><?php endif; ?>
<?php endif; ?>

<p class="grand" style="text-align:left;border:none;margin-top:22px">
  <span class="muted" style="font-family:var(--font-body)"><strong>Assumptions:</strong>
  <?php foreach ($calc['products'] as $i => $p): ?><?= $i ? ' · ' : '' ?><?= e($p['name']) ?> at <?= $f0($p['sale_price']) ?>/<?= e($p['unit_name']) ?>, <?= $f0($p['units']['low']) ?>/<?= $f0($p['units']['mid']) ?>/<?= $f0($p['units']['high']) ?> per month<?php endforeach; ?>. All figures in TZS.</span>
</p>
<p class="muted" style="border-top:1px solid var(--line-soft);padding-top:8px;font-size:11px">
  Planning scenario — not an accounting record. Figures are projections and do not appear in the organisation's cashbook, statements, or exports. Generated <?= date('Y-m-d H:i') ?> · LIPA.
</p>
</body>
</html>
