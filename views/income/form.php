<?php $isNew = empty($r['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" enctype="multipart/form-data" action="<?= $isNew ? '/income' : '/income/' . (int)$r['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Date</label><input type="date" name="date" value="<?= e($r['date'] ?? date('Y-m-d')) ?>" required></div>
    <div class="form-field"><label>Account</label>
      <select name="account_id" required>
        <option value="">—</option>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= (int)$acc['id'] ?>" <?= ((int)($r['account_id'] ?? ($accounts[0]['id'] ?? 0)) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Donor</label>
      <select name="contact_id">
        <option value="">—</option>
        <?php foreach ($contacts as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['contact_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Category</label>
      <select name="category_id">
        <option value="">—</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= ((int)($r['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Project</label>
      <select name="project_id">
        <option value="">—</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($r['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Currency</label>
      <select name="currency">
        <?php foreach (['TZS','USD'] as $cur): ?>
          <option value="<?= $cur ?>" <?= (($r['currency'] ?? 'TZS') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Amount (original currency)</label><input type="number" step="0.01" name="amount_original" value="<?= e($r['amount_original'] ?? '') ?>" required></div>
    <div class="form-field"><label>Exchange rate to TZS (USD only)</label><input type="number" step="0.000001" name="exchange_rate" value="<?= e($r['exchange_rate'] ?? '1') ?>"></div>
  </div>
  <div class="form-field"><label>Description</label><input name="description" value="<?= e($r['description'] ?? '') ?>"></div>
  <div class="form-field"><label>Reference</label><input name="reference" value="<?= e($r['reference'] ?? '') ?>"></div>
  <div class="form-field"><label>Notes</label><textarea name="notes"><?= e($r['notes'] ?? '') ?></textarea></div>
  <div class="form-field"><label>Receipt (PDF / JPG / PNG)</label><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
    <?php if (!empty($r['receipt_path'])): ?><div class="form-hint">Current: <a href="/income/<?= (int)$r['id'] ?>/receipt">View receipt</a> &middot; <a href="/income/<?= (int)$r['id'] ?>/receipt/print" target="_blank">Print</a></div><?php endif; ?>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/income" class="btn ghost">Cancel</a>
  </div>
</form>
