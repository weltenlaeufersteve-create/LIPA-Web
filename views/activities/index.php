<h1>Activities</h1>
<div class="list-toolbar">
  <span></span>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary list-new" href="/activities/new">New activity</a>
  <?php endif; ?>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>Title</th><th>Project</th><th>Photos</th><th>Cost (TZS)</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['title']) ?></td>
      <td><?= e($row['project_name']) ?></td>
      <td><?= App\Models\ActivityItem::photoCount((int)$row['id']) ?></td>
      <td><?= number_format(App\Models\ActivityItem::cost((int)$row['id']), 2) ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/activities/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/activities/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this activity?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($rows)): ?><tr><td colspan="6">No activities yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
