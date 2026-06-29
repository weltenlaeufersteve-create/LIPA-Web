<?php $isNew = empty($cat['id']); ?>
<h1><?= $isNew ? 'New category' : 'Edit category' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/categories' : '/categories/' . (int)$cat['id'] ?>">
  <label>Type
    <select name="type">
      <?php foreach (['income','expense'] as $t): ?>
        <option value="<?= $t ?>" <?= (($cat['type'] ?? 'expense') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Name <input name="name" value="<?= e($cat['name'] ?? '') ?>" required></label>
  <label>Sort order <input type="number" name="sort_order" value="<?= (int)($cat['sort_order'] ?? 0) ?>"></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($cat['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/categories" class="btn">Cancel</a>
</form>
