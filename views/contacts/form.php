<?php $isNew = empty($c['id']); ?>
<h1><?= $isNew ? 'New contact' : 'Edit contact' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="<?= $isNew ? '/contacts' : '/contacts/' . (int)$c['id'] ?>">
  <label>Type
    <select name="type">
      <?php foreach (['donor','vendor'] as $t): ?>
        <option value="<?= $t ?>" <?= (($c['type'] ?? 'donor') === $t) ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Name <input name="name" value="<?= e($c['name'] ?? '') ?>" required></label>
  <label>Email <input type="email" name="email" value="<?= e($c['email'] ?? '') ?>"></label>
  <label>Phone <input name="phone" value="<?= e($c['phone'] ?? '') ?>"></label>
  <label>Address <textarea name="address"><?= e($c['address'] ?? '') ?></textarea></label>
  <label>Notes <textarea name="notes"><?= e($c['notes'] ?? '') ?></textarea></label>
  <?php if (!$isNew): ?>
    <label><input type="checkbox" name="active" value="1" <?= ((int)($c['active'] ?? 1) === 1) ? 'checked' : '' ?>> Active</label>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/contacts" class="btn">Cancel</a>
</form>
