<?php $isNew = empty($t['id']); ?>
<h1><?= $isNew ? 'New transfer' : 'Edit transfer' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/transfers' : '/transfers/' . (int)$t['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($t['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>From account
    <select name="from_account_id" required>
      <option value="">—</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['from_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>To account
    <select name="to_account_id" required>
      <option value="">—</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['to_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Amount (TZS) <input type="number" step="0.01" name="amount_tzs" value="<?= e($t['amount_tzs'] ?? '') ?>" required></label>
  <label>Description <input name="description" value="<?= e($t['description'] ?? '') ?>"></label>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/transfers" class="btn">Cancel</a>
</form>
