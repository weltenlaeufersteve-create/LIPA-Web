<?php $isNew = empty($p['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/projects' : '/projects/' . (int)$p['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Name</label><input name="name" value="<?= e($p['name'] ?? '') ?>" required></div>
    <div class="form-field"><label>Code</label><input name="code" value="<?= e($p['code'] ?? '') ?>"></div>
  </div>
  <div class="form-field"><label>Description</label><textarea name="description"><?= e($p['description'] ?? '') ?></textarea></div>
  <?php if (!$isNew): ?>
    <div class="form-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" <?= ((int)($p['active'] ?? 1) === 1) ? 'checked' : '' ?> style="width:auto"> Active</label></div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/projects" class="btn ghost">Cancel</a>
  </div>
</form>
