<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($rows) ?> activit<?= count($rows) === 1 ? 'y' : 'ies' ?></span>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn list-new" href="/activities/new">+ New activity</a>
  <?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Date</th><th>Title</th><th>Project</th><th class="r">Photos</th><th class="r">Cost (TZS)</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="muted-cell num"><?= e($row['date']) ?></td>
        <td class="name"><?= e($row['title']) ?></td>
        <td><?php if (!empty($row['project_name'])): ?><span class="tag"><?= e($row['project_name']) ?></span><?php endif; ?></td>
        <td class="r muted-cell num"><?= App\Models\ActivityItem::photoCount((int)$row['id']) ?></td>
        <td class="r money"><?= number_format(App\Models\ActivityItem::cost((int)$row['id']), 2) ?></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/activities/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/activities/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this activity?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="muted-cell">No activities yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
