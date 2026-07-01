<?php
$activeTab = 'users';
include dirname(__DIR__) . '/admin/_tabs.php';
$roleClass = ['admin' => 'role-admin', 'editor' => 'role-coord', 'viewer' => 'role-acct'];
?>
<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($users) ?> user<?= count($users) === 1 ? '' : 's' ?></span>
  <a class="btn list-new" href="/users/new">+ New user</a>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $row): ?>
      <tr>
        <td class="name"><?= e($row['name']) ?></td>
        <td class="muted-cell"><?= e($row['email']) ?></td>
        <td><span class="role-badge <?= $roleClass[$row['role']] ?? '' ?>"><span class="bdot"></span><?= e(\App\role_label($row['role'])) ?></span></td>
        <td><span class="badge <?= (int)$row['active'] === 1 ? 'on' : 'off' ?>"><span class="bdot"></span><?= (int)$row['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
        <td class="r">
          <div class="rowact">
            <a class="edit" href="/users/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
            <form method="post" action="/users/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this user?">
              <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($users)): ?><tr><td colspan="5" class="muted-cell">No users yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
