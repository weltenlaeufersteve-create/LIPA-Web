<?php $isNew = empty($a['id']); ?>
<h1><?= $isNew ? 'New activity' : 'Edit activity' ?></h1>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" action="<?= $isNew ? '/activities' : '/activities/' . (int)$a['id'] ?>">
  <label>Date <input type="date" name="date" value="<?= e($a['date'] ?? date('Y-m-d')) ?>" required></label>
  <label>Title <input name="title" value="<?= e($a['title'] ?? '') ?>" required></label>
  <label>Description <textarea name="description" rows="4"><?= e($a['description'] ?? '') ?></textarea></label>
  <label>Project
    <select name="project_id">
      <option value="">—</option>
      <?php foreach ($projects as $p): ?>
        <option value="<?= (int)$p['id'] ?>" <?= ((int)($a['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <h3>Photos (max 5, JPG/PNG)</h3>
  <?php if (!empty($photos)): ?>
    <div class="photo-grid">
      <?php foreach ($photos as $ph): ?>
        <div class="photo-thumb">
          <img src="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>" alt="">
          <div><button type="submit" formaction="/activities/<?= (int)$a['id'] ?>/photo/<?= (int)$ph['id'] ?>/delete" formmethod="post" class="btn-link-danger" data-confirm="Delete this photo?">Delete</button></div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <label>Add photos <input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple></label>
  <small>Large photos are resized automatically. <?= !empty($a['id']) ? (5 - count($photos)) . ' slot(s) left.' : 'Up to 5.' ?></small>

  <h3>Linked expenses</h3>
  <p><small>Tick the expenses that belong to this activity (unassigned expenses + ones already on it).</small></p>
  <div class="table-wrap picker-scroll">
  <table class="data-table">
    <thead><tr><th></th><th>Date</th><th>Category</th><th>Description</th><th>Amount (TZS)</th></tr></thead>
    <tbody>
    <?php foreach ($available as $ex): ?>
      <tr>
        <td><input type="checkbox" name="expense_ids[]" value="<?= (int)$ex['id'] ?>" <?= in_array((int)$ex['id'], $linked, true) ? 'checked' : '' ?>></td>
        <td><?= e($ex['date']) ?></td>
        <td><?= e($ex['category_name']) ?></td>
        <td><?= e($ex['description']) ?></td>
        <td><?= number_format((float)$ex['amount_tzs'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($available)): ?><tr><td colspan="5">No expenses available to link.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

  <p class="form-actions">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="/activities" class="btn">Cancel</a>
  </p>
</form>
