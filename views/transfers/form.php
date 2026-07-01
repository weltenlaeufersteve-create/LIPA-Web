<?php $isNew = empty($t['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/transfers' : '/transfers/' . (int)$t['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Date</label><input type="date" name="date" value="<?= e($t['date'] ?? date('Y-m-d')) ?>" required></div>
    <div class="form-field"><label>Amount (TZS)</label><input type="number" step="0.01" name="amount_tzs" value="<?= e($t['amount_tzs'] ?? '') ?>" required></div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>From account</label>
      <select name="from_account_id" required>
        <option value="">—</option>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['from_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>To account</label>
      <select name="to_account_id" required>
        <option value="">—</option>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= (int)$acc['id'] ?>" <?= ((int)($t['to_account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-field"><label>Description</label><input name="description" value="<?= e($t['description'] ?? '') ?>"></div>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/transfers" class="btn ghost">Cancel</a>
  </div>
</form>
