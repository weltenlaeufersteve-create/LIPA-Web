<?php $isNew = empty($u['id']); ?>
<h1><?= $isNew ? 'New user' : 'Edit user' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/users' : '/users/' . (int)$u['id'] ?>">
  <label>Name <input name="name" value="<?= e($u['name'] ?? '') ?>" required></label>
  <label>Email <input type="email" name="email" value="<?= e($u['email'] ?? '') ?>" required></label>
  <label>Role
    <select name="role">
      <?php foreach (['admin','editor','viewer'] as $r): ?>
        <option value="<?= $r ?>" <?= (($u['role'] ?? 'viewer') === $r) ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Password <input type="password" name="password" <?= $isNew ? 'required' : '' ?>>
    <?php if (!$isNew): ?><small>Leave blank to keep current password.</small><?php endif; ?>
  </label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($u['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/users" class="btn">Cancel</a>
</form>
