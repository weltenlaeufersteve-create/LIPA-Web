<?php $isNew = empty($u['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/users' : '/users/' . (int)$u['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Name</label><input name="name" value="<?= e($u['name'] ?? '') ?>" required></div>
    <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= e($u['email'] ?? '') ?>" required></div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Role</label>
      <select name="role">
        <?php foreach (['admin','editor','viewer'] as $r): ?>
          <option value="<?= $r ?>" <?= (($u['role'] ?? 'viewer') === $r) ? 'selected' : '' ?>><?= e(\App\role_label($r)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Password</label><input type="password" name="password" <?= $isNew ? 'required' : '' ?>>
      <?php if (!$isNew): ?><div class="form-hint">Leave blank to keep current password.</div><?php endif; ?>
    </div>
  </div>
  <?php if (!$isNew): ?>
    <div class="form-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" <?= ((int)($u['active'] ?? 1) === 1) ? 'checked' : '' ?> style="width:auto"> Active</label></div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/users" class="btn ghost">Cancel</a>
  </div>
</form>
