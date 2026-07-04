<?php
$isNew   = empty($s['id']);
$canEdit = App\Auth::is('admin','editor');
$ro      = $canEdit ? '' : 'disabled';
$sc      = $s ?: [];
$midcol  = 'style="background:var(--accent-quiet)"';
?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" id="budget-form" method="post" action="<?= $isNew ? '/budget' : '/budget/' . (int)$s['id'] ?>" style="max-width:none">
  <div class="row-between" style="margin-bottom:16px">
    <div class="form-hint" style="margin:0;max-width:520px">Planning only — a scenario never creates entries in Income/Expenses and never appears in statements, balances, or the Excel export.</div>
    <div style="display:flex;gap:8px">
      <?php if (!$isNew): ?><a class="btn ghost" href="/budget/<?= (int)$s['id'] ?>/print" target="_blank">Print / PDF</a><?php endif; ?>
      <?php if ($canEdit): ?><button type="submit" class="btn">Save scenario</button><?php endif; ?>
    </div>
  </div>

  <div class="form-grid">
    <div class="form-field"><label>Scenario name</label><input name="name" value="<?= e($sc['name'] ?? '') ?>" required <?= $ro ?>></div>
    <div class="form-field"><label>Status</label>
      <select name="status" <?= $ro ?>>
        <?php foreach (['draft','active','archived'] as $st): ?>
          <option value="<?= $st ?>" <?= (($sc['status'] ?? 'draft') === $st) ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Project (optional)</label>
      <select name="project_id" <?= $ro ?>>
        <option value="">—</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($sc['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Description</label><input name="description" value="<?= e($sc['description'] ?? '') ?>" <?= $ro ?>></div>
  </div>

  <!-- PRODUCTS -->
  <h3 class="section-title" style="margin-top:26px">Products</h3>
  <p class="fieldset-hint">Each product in this activity — price, cost to make one, and monthly volume (pessimistic / realistic / optimistic). Add as many as you like (soap = 1, a pottery line = several).</p>
  <div class="card table-card"><div class="table-scroll">
    <table class="ledger" id="tbl-products">
      <thead><tr><th>Product</th><th>Unit</th><th class="r">Price</th><th class="r">Cost</th><th class="r">Margin</th><th class="r">Low</th><th class="r">Mid</th><th class="r">High</th><th class="r">Contrib/mo</th><th></th></tr></thead>
      <tbody>
        <?php $plist = $products ?: [null]; foreach ($plist as $p): ?>
        <tr class="brow">
          <td><input name="p_name[]" value="<?= e($p['name'] ?? '') ?>" placeholder="e.g. Decorative bowl" <?= $ro ?>></td>
          <td><input name="p_unit[]" value="<?= e($p['unit_name'] ?? 'unit') ?>" style="max-width:64px" <?= $ro ?>></td>
          <td class="r"><input class="bnum r" name="p_price[]" inputmode="numeric" value="<?= $p ? (float)$p['sale_price'] : '' ?>" style="max-width:100px" <?= $ro ?>></td>
          <td class="r"><input class="bnum r" name="p_cost[]" inputmode="numeric" value="<?= $p ? (float)$p['unit_cost'] : '' ?>" style="max-width:100px" <?= $ro ?>></td>
          <td class="r money" data-margin>—</td>
          <td class="r"><input class="bnum r" name="p_low[]" inputmode="numeric" value="<?= $p ? (int)$p['units_low'] : '' ?>" style="max-width:64px" <?= $ro ?>></td>
          <td class="r"><input class="bnum r" name="p_mid[]" inputmode="numeric" value="<?= $p ? (int)$p['units_mid'] : '' ?>" style="max-width:64px" <?= $ro ?>></td>
          <td class="r"><input class="bnum r" name="p_high[]" inputmode="numeric" value="<?= $p ? (int)$p['units_high'] : '' ?>" style="max-width:64px" <?= $ro ?>></td>
          <td class="r money" data-contrib>—</td>
          <td class="r"><?php if ($canEdit): ?><button type="button" class="btn-link-danger" data-row-remove aria-label="Remove">✕</button><?php endif; ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
  <?php if ($canEdit): ?>
    <p style="margin:8px 0 0;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
      <button type="button" class="btn ghost" data-add-row="tbl-products">+ Add product</button>
      <span class="fieldset-hint" style="margin:0">Cost helper: <input class="bnum" id="bh-total" inputmode="numeric" placeholder="batch total" style="max-width:110px"> ÷ <input class="bnum" id="bh-yield" inputmode="numeric" placeholder="per batch" style="max-width:100px"> = <b id="bh-result">—</b> / unit</span>
    </p>
  <?php endif; ?>

  <!-- COSTS -->
  <div class="form-grid" style="margin-top:26px">
    <div>
      <h3 class="section-title">Start-up costs</h3>
      <p class="fieldset-hint">One-time investment.</p>
      <div class="card table-card"><table class="ledger" id="tbl-onetime">
        <thead><tr><th>Item</th><th class="r">Amount</th><th></th></tr></thead>
        <tbody>
          <?php $olist = $one_time ?: [null]; foreach ($olist as $o): ?>
          <tr class="brow">
            <td><input name="ot_name[]" value="<?= e($o['name'] ?? '') ?>" placeholder="e.g. Kiln" <?= $ro ?>></td>
            <td class="r"><input class="bnum r" name="ot_amount[]" inputmode="numeric" value="<?= $o ? (float)$o['amount'] : '' ?>" <?= $ro ?>></td>
            <td class="r"><?php if ($canEdit): ?><button type="button" class="btn-link-danger" data-row-remove aria-label="Remove">✕</button><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td>Total start-up</td><td class="r money" id="r-onetime">—</td><td></td></tr>
          <tr><td>− Funded by partner</td><td class="r"><input class="bnum r" name="funded_amount" inputmode="numeric" value="<?= (float)($sc['funded_amount'] ?? 0) ?>" <?= $ro ?>></td><td></td></tr>
          <tr><td><b>= NGO share to recover</b></td><td class="r money" id="r-netstartup" style="color:var(--accent)">—</td><td></td></tr>
        </tfoot>
      </table></div>
      <?php if ($canEdit): ?><p style="margin:8px 0 0"><button type="button" class="btn ghost" data-add-row="tbl-onetime">+ Add start-up cost</button></p><?php endif; ?>
    </div>
    <div>
      <h3 class="section-title">Fixed costs / month</h3>
      <p class="fieldset-hint">Run whether you sell or not.</p>
      <div class="card table-card"><table class="ledger" id="tbl-fixed">
        <thead><tr><th>Item</th><th class="r">Amount</th><th></th></tr></thead>
        <tbody>
          <?php $flist = $monthly_fixed ?: [null]; foreach ($flist as $f): ?>
          <tr class="brow">
            <td><input name="mf_name[]" value="<?= e($f['name'] ?? '') ?>" placeholder="e.g. Rent share" <?= $ro ?>></td>
            <td class="r"><input class="bnum r" name="mf_amount[]" inputmode="numeric" value="<?= $f ? (float)$f['amount'] : '' ?>" <?= $ro ?>></td>
            <td class="r"><?php if ($canEdit): ?><button type="button" class="btn-link-danger" data-row-remove aria-label="Remove">✕</button><?php endif; ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td>Total / month</td><td class="r money" id="r-fixed">—</td><td></td></tr></tfoot>
      </table></div>
      <?php if ($canEdit): ?><p style="margin:8px 0 0"><button type="button" class="btn ghost" data-add-row="tbl-fixed">+ Add fixed cost</button></p><?php endif; ?>
    </div>
  </div>

  <!-- RESULTS -->
  <h3 class="section-title" style="margin-top:26px">Results</h3>
  <div class="kpis">
    <div class="card kpi"><div class="kpi-label">Revenue / mo (realistic)</div><div class="kpi-value num" id="r-revenue">—</div><div class="kpi-tag">TZS</div></div>
    <div class="card kpi"><div class="kpi-label">Break-even</div><div class="kpi-value num" id="r-breakeven">—</div><div class="kpi-tag">NGO share ÷ realistic profit</div></div>
    <div class="card kpi hero"><div class="kpi-label">Monthly profit — realistic</div><div class="kpi-value num" id="r-profit">—</div><div class="kpi-tag">After fixed costs</div></div>
  </div>

  <h3 class="section-title">The three cases</h3>
  <div class="card table-card"><div class="table-scroll"><table class="ledger">
    <thead><tr><th>Per month</th><th class="r">Pessimistic</th><th class="r" <?= $midcol ?>>Realistic</th><th class="r">Optimistic</th></tr></thead>
    <tbody>
      <tr><td class="muted-cell">Units sold</td><td class="r num" id="c-low-units">—</td><td class="r num" <?= $midcol ?> id="c-mid-units">—</td><td class="r num" id="c-high-units">—</td></tr>
      <tr><td class="muted-cell">Revenue</td><td class="r money" id="c-low-rev">—</td><td class="r money" <?= $midcol ?> id="c-mid-rev">—</td><td class="r money" id="c-high-rev">—</td></tr>
      <tr><td class="muted-cell">Variable costs</td><td class="r money" id="c-low-var">—</td><td class="r money" <?= $midcol ?> id="c-mid-var">—</td><td class="r money" id="c-high-var">—</td></tr>
      <tr><td class="muted-cell">Fixed costs</td><td class="r money" id="c-low-fixed">—</td><td class="r money" <?= $midcol ?> id="c-mid-fixed">—</td><td class="r money" id="c-high-fixed">—</td></tr>
      <tr><td><b>Profit</b></td><td class="r money" id="c-low-profit">—</td><td class="r money" <?= $midcol ?> id="c-mid-profit">—</td><td class="r money" id="c-high-profit">—</td></tr>
      <tr><td class="muted-cell">Break-even</td><td class="r" id="c-low-be">—</td><td class="r" <?= $midcol ?> id="c-mid-be">—</td><td class="r" id="c-high-be">—</td></tr>
    </tbody>
  </table></div></div>

  <!-- ALLOCATIONS -->
  <h3 class="section-title">What the profit pays for</h3>
  <p class="fieldset-hint">Realistic case, covered in order (top first).</p>
  <div class="card table-card"><table class="ledger" id="tbl-alloc">
    <thead><tr><th>Item</th><th class="r">Monthly amount</th><th style="width:38%">Covered</th><th></th></tr></thead>
    <tbody>
      <?php $alist = $allocations ?: [null]; foreach ($alist as $al): ?>
      <tr class="brow">
        <td><input name="al_name[]" value="<?= e($al['name'] ?? '') ?>" placeholder="e.g. Health insurance" <?= $ro ?>></td>
        <td class="r"><input class="bnum r" name="al_amount[]" inputmode="numeric" value="<?= $al ? (float)$al['monthly_amount'] : '' ?>" <?= $ro ?>></td>
        <td><div class="cat-bar-wrap"><div class="cat-bar"><span data-alloc-bar style="width:0%"></span></div><span class="muted-cell num" data-alloc-pct style="min-width:40px;text-align:right">—</span></div></td>
        <td class="r"><?php if ($canEdit): ?><button type="button" class="btn-link-danger" data-row-remove aria-label="Remove">✕</button><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php if ($canEdit): ?><p style="margin:8px 0 0"><button type="button" class="btn ghost" data-add-row="tbl-alloc">+ Add allocation</button></p><?php endif; ?>
  <p class="fieldset-hint" id="r-allocnote" style="margin-top:10px">—</p>

  <?php if ($canEdit): ?>
    <div class="form-actions"><button type="submit" class="btn">Save scenario</button><a href="/budget" class="btn ghost">Cancel</a></div>
  <?php endif; ?>
</form>
