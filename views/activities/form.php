<?php $isNew = empty($a['id']); ?>
<?php if (!empty($error)): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
<form class="form-card" method="post" enctype="multipart/form-data" action="<?= $isNew ? '/activities' : '/activities/' . (int)$a['id'] ?>" style="max-width:820px">
  <div class="form-grid">
    <div class="form-field"><label>Date</label><input type="date" name="date" value="<?= e($a['date'] ?? date('Y-m-d')) ?>" required></div>
    <div class="form-field"><label>Project</label>
      <select name="project_id">
        <option value="">—</option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($a['project_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-field"><label>Title</label><input name="title" value="<?= e($a['title'] ?? '') ?>" placeholder="e.g. School supplies distribution — Karatu" required></div>
  <div class="form-field"><label>Description</label><textarea name="description" rows="4" placeholder="What happened, who took part, and the outcome."><?= e($a['description'] ?? '') ?></textarea></div>

  <div class="fieldset-label">Photos <span style="font-weight:500;color:var(--faint);font-size:13px">· max 5, JPG/PNG</span></div>
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
  <label class="upload-btn" style="margin-bottom:6px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>Add photos
    <input type="file" name="photos[]" accept=".jpg,.jpeg,.png" multiple style="display:none">
  </label>
  <div class="form-hint">Large photos are resized automatically. <?= !empty($a['id']) ? (5 - count($photos)) . ' slot(s) left.' : 'Up to 5.' ?></div>

  <div class="fieldset-label">Linked expenses</div>
  <p class="fieldset-hint">Tick the expenses that belong to this activity. Search to narrow the list by description, category or date.</p>
  <div class="search" style="margin-bottom:14px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
    <input id="expSearch" type="text" placeholder="Search expenses…" autocomplete="off">
  </div>
  <div class="card table-card picker-scroll" id="expPicker">
    <table class="ledger">
      <thead><tr><th style="width:44px"></th><th>Date</th><th>Category</th><th>Description</th><th class="r">Amount (TZS)</th></tr></thead>
      <tbody>
      <?php foreach ($available as $ex): $checked = in_array((int)$ex['id'], $linked, true); ?>
        <tr data-text="<?= e(strtolower(($ex['date'] ?? '') . ' ' . ($ex['category_name'] ?? '') . ' ' . ($ex['description'] ?? ''))) ?>"<?= $checked ? ' class="checked"' : '' ?>>
          <td><input class="check" type="checkbox" name="expense_ids[]" value="<?= (int)$ex['id'] ?>" <?= $checked ? 'checked' : '' ?>></td>
          <td class="muted-cell num"><?= e($ex['date']) ?></td>
          <td><?php if (!empty($ex['category_name'])): ?><span class="tag"><?= e($ex['category_name']) ?></span><?php endif; ?></td>
          <td class="muted-cell"><?= e($ex['description']) ?></td>
          <td class="r money"><?= number_format((float)$ex['amount_tzs'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (empty($available)): ?><div class="no-results">No expenses available to link.</div><?php endif; ?>
    <div class="no-results" id="expNoResults" style="display:none">No expenses match that search.</div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn">Save</button>
    <a href="/activities" class="btn ghost">Cancel</a>
  </div>
</form>
