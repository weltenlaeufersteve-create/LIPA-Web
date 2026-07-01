<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<?php $isNew = empty($a['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/accounts' : '/accounts/' . (int)$a['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Name</label><input name="name" value="<?= e($a['name'] ?? '') ?>" required></div>
    <div class="form-field"><label>Type</label>
      <select name="type">
        <?php foreach (['bank','cash','other'] as $t): ?>
          <option value="<?= $t ?>" <?= (($a['type'] ?? 'bank') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Opening balance (TZS)</label><input type="number" step="0.01" name="opening_balance" value="<?= e($a['opening_balance'] ?? '0') ?>"></div>
    <div class="form-field"><label>Opening balance date</label><input type="date" name="opening_balance_date" value="<?= e($a['opening_balance_date'] ?? '') ?>"></div>
  </div>
  <?php if (!$isNew): ?>
    <div class="form-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" <?= ((int)($a['active'] ?? 1) === 1) ? 'checked' : '' ?> style="width:auto"> Active</label></div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/accounts" class="btn ghost">Cancel</a>
  </div>
</form>
