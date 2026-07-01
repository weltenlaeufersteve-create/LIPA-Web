<?php $isNew = empty($c['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" action="<?= $isNew ? '/contacts' : '/contacts/' . (int)$c['id'] ?>">
  <div class="form-grid">
    <div class="form-field"><label>Type</label>
      <select name="type">
        <?php foreach (['donor','vendor'] as $t): ?>
          <option value="<?= $t ?>" <?= (($c['type'] ?? 'donor') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field"><label>Name</label><input name="name" value="<?= e($c['name'] ?? '') ?>" required></div>
  </div>
  <div class="form-grid">
    <div class="form-field"><label>Email</label><input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"></div>
    <div class="form-field"><label>Phone</label><input name="phone" value="<?= e($c['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-field"><label>Address</label><textarea name="address"><?= e($c['address'] ?? '') ?></textarea></div>
  <div class="form-field"><label>Notes</label><textarea name="notes"><?= e($c['notes'] ?? '') ?></textarea></div>
  <?php if (!$isNew): ?>
    <div class="form-field"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="active" value="1" <?= ((int)($c['active'] ?? 1) === 1) ? 'checked' : '' ?> style="width:auto"> Active</label></div>
  <?php endif; ?>
  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/contacts" class="btn ghost">Cancel</a>
  </div>
</form>
