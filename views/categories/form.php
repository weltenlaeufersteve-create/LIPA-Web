<?php $isNew = empty($cat['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/categories' : '/categories/' . (int)$cat['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Type</label>
      <select name="type">
        <?php foreach (['income','expense'] as $t): ?>
          <option value="<?= $t ?>" <?= (($cat['type'] ?? 'expense') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>"></div>
  </div>
  <div class="form-field"><label>Name</label><input name="name" value="<?= e($cat['name'] ?? '') ?>" required></div>
  <?php if (!$isNew): ?>
    <div class="form-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" <?= ((int)($cat['active'] ?? 1) === 1) ? 'checked' : '' ?> style="width:auto"> Active</label></div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/categories" class="btn ghost">Cancel</a>
  </div>
</form>
