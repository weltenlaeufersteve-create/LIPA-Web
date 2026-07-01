<form method="get" action="<?= e($action) ?>" class="filterbar">
  <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($f['date_from'] ?? '') ?>"></div>
  <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($f['date_to'] ?? '') ?>"></div>
  <div class="field"><label>Project</label>
    <select name="project_id">
      <option value="">All</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($f['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Category</label>
    <select name="category_id">
      <option value="">All</option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= (int)$cat['id'] ?>" <?= ((int)($f['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field"><label>Account</label>
    <select name="account_id">
      <option value="">All</option>
      <?php foreach ($accounts as $acc): ?>
        <option value="<?= (int)$acc['id'] ?>" <?= ((int)($f['account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="actions">
    <button type="submit" class="btn">Filter</button>
    <a class="btn ghost" href="<?= e($action) ?>">Clear</a>
  </div>
</form>
