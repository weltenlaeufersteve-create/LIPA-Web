<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<?php $isNew = empty($a['id']); ?>
<h1><?= $isNew ? 'New account' : 'Edit account' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/accounts' : '/accounts/' . (int)$a['id'] ?>">
  <label>Name <input name="name" value="<?= e($a['name'] ?? '') ?>" required></label>
  <label>Type
    <select name="type">
      <?php foreach (['bank','cash','other'] as $t): ?>
        <option value="<?= $t ?>" <?= (($a['type'] ?? 'bank') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Opening balance (TZS) <input type="number" step="0.01" name="opening_balance" value="<?= e($a['opening_balance'] ?? '0') ?>"></label>
  <label>Opening balance date <input type="date" name="opening_balance_date" value="<?= e($a['opening_balance_date'] ?? '') ?>"></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($a['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/accounts" class="btn">Cancel</a>
</form>
