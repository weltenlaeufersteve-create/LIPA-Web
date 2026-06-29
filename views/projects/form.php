<?php $isNew = empty($p['id']); ?>
<h1><?= $isNew ? 'New project' : 'Edit project' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/projects' : '/projects/' . (int)$p['id'] ?>">
  <label>Name <input name="name" value="<?= e($p['name'] ?? '') ?>" required></label>
  <label>Code <input name="code" value="<?= e($p['code'] ?? '') ?>"></label>
  <label>Description <textarea name="description"><?= e($p['description'] ?? '') ?></textarea></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($p['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/projects" class="btn">Cancel</a>
</form>
