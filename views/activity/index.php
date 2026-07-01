<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>Description</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $a): ?>
      <tr>
        <td class="muted-cell num"><?= e($a['created_at']) ?></td>
        <td class="name"><?= e($a['user_name'] ?? 'system') ?></td>
        <td><span class="tag"><?= e($a['action']) ?></span></td>
        <td class="muted-cell"><?= e($a['entity_type']) ?><?= $a['entity_id'] ? ' #' . (int)$a['entity_id'] : '' ?></td>
        <td class="muted-cell"><?= e($a['description']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="5" class="muted-cell">No activity yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
