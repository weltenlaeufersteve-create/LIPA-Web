<?php $isNew = empty($r['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" enctype="multipart/form-data" action="<?= $isNew ? '/expenses' : '/expenses/' . (int)$r['id'] ?>">
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
    <div class="form-field"><label>Vendor</label>
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
    <div class="form-field"><label>Amount (TZS)</label><input type="number" step="0.01" name="amount_tzs" value="<?= e($r['amount_tzs'] ?? '') ?>" required></div>
  </div>
  <div class="form-field"><label>Description</label><input name="description" value="<?= e($r['description'] ?? '') ?>"></div>
  <div class="form-field"><label>Reference</label><input name="reference" value="<?= e($r['reference'] ?? '') ?>"></div>
  <div class="form-field"><label>Notes</label><textarea name="notes"><?= e($r['notes'] ?? '') ?></textarea></div>
  <div class="form-field"><label>Receipt (PDF / JPG / PNG)</label><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png">
    <?php if (!empty($r['receipt_path'])): ?><div class="form-hint">Current: <a href="/expenses/<?= (int)$r['id'] ?>/receipt">View receipt</a> &middot; <a href="/expenses/<?= (int)$r['id'] ?>/receipt/print" target="_blank">Print</a></div><?php endif; ?>
  </div>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/expenses" class="btn ghost">Cancel</a>
  </div>
</form>
