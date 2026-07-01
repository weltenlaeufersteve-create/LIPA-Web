<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?></span>
  <a class="btn list-new" href="/projects/new">+ New project</a>
</div>
<div class="card table-card">
  <table class="ledger">
    <thead><tr><th>Name</th><th>Code</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($projects as $row): ?>
      <tr>
        <td class="name"><?= e($row['name']) ?></td>
        <td class="muted-cell"><?= e($row['code']) ?></td>
        <td><span class="badge <?= (int)$row['active'] === 1 ? 'on' : 'off' ?>"><span class="bdot"></span><?= (int)$row['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
        <td class="r">
          <div class="rowact">
            <a class="edit" href="/projects/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
            <form method="post" action="/projects/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this project?">
              <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($projects)): ?><tr><td colspan="4" class="muted-cell">No projects yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
