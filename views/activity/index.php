<h1>Activity log</h1>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $a): ?>
    <tr>
      <td><?= e($a['created_at']) ?></td>
      <td><?= e($a['user_name'] ?? 'system') ?></td>
      <td><?= e($a['action']) ?></td>
      <td><?= e($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td>
      <td><?= e($a['description']) ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($rows)): ?><tr><td colspan="5">No activity yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
