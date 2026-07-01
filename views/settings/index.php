<?php
$activeTab = 'organisation';
include dirname(__DIR__) . '/admin/_tabs.php';
$accent  = \App\hex_color($s['accent_color'] ?? null);
$presets = ['#C0175B','#0E7C7B','#2456B0','#C77A0A','#2E7D4F'];
$canEdit = \App\Auth::is('admin');
$ro = $canEdit ? '' : 'disabled';
?>
<?php if (!empty($saved)): ?><div class="alert" style="background:var(--accent-soft);color:var(--accent);padding:10px 13px;border-radius:var(--radius-sm);margin:0 0 16px;font-weight:600">Settings saved.</div><?php endif; ?>
<?php if (!$canEdit): ?><div class="form-hint" style="margin:0 0 14px">Read-only — organisation details are managed by an administrator.</div><?php endif; ?>
<form class="form-card" method="post" enctype="multipart/form-data" action="/settings">
  <div class="form-field"><label>Organisation name</label><input name="org_name" value="<?= e($s['org_name'] ?? '') ?>" <?= $ro ?>></div>
  <div class="form-field"><label>Address</label><textarea name="org_address" <?= $ro ?>><?= e($s['org_address'] ?? '') ?></textarea></div>
  <div class="form-field"><label>Email</label><input type="email" name="org_email" value="<?= e($s['org_email'] ?? '') ?>" <?= $ro ?>></div>
  <div class="form-grid">
    <div class="form-field"><label>Tax ID</label><input name="tax_id" value="<?= e($s['tax_id'] ?? '') ?>" <?= $ro ?>></div>
    <div class="form-field"><label>NGO registration no.</label><input name="ngo_number" value="<?= e($s['ngo_number'] ?? '') ?>" <?= $ro ?>></div>
  </div>
  <div class="form-field"><label>Base currency</label>
    <select name="base_currency" <?= $ro ?>>
      <?php foreach (['TZS'=>'TZS — Tanzanian Shilling','KES'=>'KES — Kenyan Shilling','UGX'=>'UGX — Ugandan Shilling','USD'=>'USD — US Dollar','EUR'=>'EUR — Euro'] as $code=>$label): ?>
        <option value="<?= $code ?>" <?= (($s['base_currency'] ?? 'TZS') === $code) ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-field"><label>Accent colour</label>
    <div class="accent-picker">
      <input type="color" name="accent_color" id="accentInput" value="<?= e($accent) ?>" style="width:44px;height:38px;padding:3px;border-radius:8px;border:1px solid var(--line);background:var(--surface-2);cursor:pointer" <?= $ro ?>>
      <span class="accent-hex" id="accentHex"><?= e($accent) ?></span>
      <?php foreach ($presets as $sw): ?>
        <button type="button" class="sw" style="background:<?= $sw ?>" data-accent="<?= $sw ?>" aria-label="<?= $sw ?>" <?= strcasecmp($sw, $accent) === 0 ? 'aria-pressed="true"' : '' ?> <?= $ro ?>></button>
      <?php endforeach; ?>
    </div>
    <div class="form-hint">Drives the highlight colour across the whole app.</div>
  </div>
  <div class="form-field"><label>Logo (PNG / JPG / SVG)</label>
    <?php if ($canEdit): ?><input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg">
    <div class="form-hint">Recommended: a <strong>square</strong> logo, about <strong>240×240&nbsp;px</strong> (transparent PNG or SVG). Shown in the sidebar badge next to the organisation name; falls back to the name's initial, then LIPA.</div><?php endif; ?>
    <?php if (!empty($s['logo'])): ?><div style="margin-top:10px">
      <div class="form-hint" style="margin-bottom:6px">Current logo</div>
      <img src="<?= asset('/uploads/' . $s['logo']) ?>" alt="logo" style="display:block;max-width:220px;border:1px solid var(--line);border-radius:8px;padding:6px;background:var(--surface-2)">
    </div><?php endif; ?>
  </div>
  <?php if ($canEdit): ?>
    <div class="form-actions"><button type="submit" class="btn">Save settings</button></div>
  <?php endif; ?>
</form>
