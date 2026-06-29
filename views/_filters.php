<form method="get" action="<?= e($action) ?>" class="filters">
  <div class="filter-row">
    <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from'] ?? '') ?>"></label>
    <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to'] ?? '') ?>"></label>
  </div>
  <div class="filter-row">
    <label style="margin:0">Project
      <select name="project_id">
        <option value="">All</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($f['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="margin:0">Category
      <select name="category_id">
        <option value="">All</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= (int)$cat['id'] ?>" <?= ((int)($f['category_id'] ?? 0) === (int)$cat['id']) ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="margin:0">Account
      <select name="account_id">
        <option value="">All</option>
        <?php foreach ($accounts as $acc): ?>
          <option value="<?= (int)$acc['id'] ?>" <?= ((int)($f['account_id'] ?? 0) === (int)$acc['id']) ? 'selected' : '' ?>><?= e($acc['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit" class="btn">Filter</button>
    <a class="btn" href="<?= e($action) ?>">Clear</a>
  </div>
</form>
