<?php $activeTab = 'organisation'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<h1>Organisation</h1>
<?php if (!empty($saved)): ?><div class="alert" style="background:var(--accent-subtle);padding:10px 12px;border-radius:8px;margin:12px 0">Settings saved.</div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="/settings">
  <label>Organisation name <input name="org_name" value="<?= e($s['org_name'] ?? '') ?>"></label>
  <label>Address <textarea name="org_address"><?= e($s['org_address'] ?? '') ?></textarea></label>
  <label>Email <input type="email" name="org_email" value="<?= e($s['org_email'] ?? '') ?>"></label>
  <label>Base currency
    <select name="base_currency">
      <?php foreach (['TZS','USD','EUR'] as $cur): ?>
        <option value="<?= $cur ?>" <?= (($s['base_currency'] ?? 'TZS') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Logo (PNG/JPG/SVG) <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg"></label>
  <?php if (!empty($s['logo'])): ?><p>Current logo: <img src="/uploads/<?= e($s['logo']) ?>" alt="logo" style="max-height:48px;vertical-align:middle"></p><?php endif; ?>
  <button type="submit" class="btn btn-primary">Save settings</button>
</form>
