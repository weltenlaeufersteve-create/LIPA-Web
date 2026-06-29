<?php $isNew = empty($r['id']); ?>
<h1><?= $isNew ? 'New income' : 'Edit income' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $isNew ? '/income' : '/income/' . (int)$r['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($r['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>Donor
    <select name="contact_id">
      <option value="">—</option>
      <?php foreach ($contacts as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ((int)($r['contact_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Category
    <select name="category_id">
      <option value="">—</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($r['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Project
    <select name="project_id">
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($r['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Description <input name="description" value="<?= e($r['description'] ?? '') ?>"></label>
  <label>Currency
    <select name="currency">
      <?php foreach (['TZS','USD'] as $cur): ?>
        <option value="<?= $cur ?>" <?= (($r['currency'] ?? 'TZS') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Amount (original currency) <input type="number" step="0.01" name="amount_original" value="<?= e($r['amount_original'] ?? '') ?>" required></label>
  <label>Exchange rate to TZS (only for USD) <input type="number" step="0.000001" name="exchange_rate" value="<?= e($r['exchange_rate'] ?? '1') ?>"></label>
  <label>Reference <input name="reference" value="<?= e($r['reference'] ?? '') ?>"></label>
  <label>Notes <textarea name="notes"><?= e($r['notes'] ?? '') ?></textarea></label>
  <label>Receipt (PDF/JPG/PNG) <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></label>
  <?php if (!empty($r['receipt_path'])): ?><p>Current receipt: <a href="/income/<?= (int)$r['id'] ?>/receipt">View</a></p><?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/income" class="btn">Cancel</a>
</form>
