<?php $activeTab = 'users'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Users</h1>
  <a class="btn btn-primary" href="/users/new">New user</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($users as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['role']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/users/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/users/<?= (int)$row['id'] ?>/delete"
              style="display:inline" data-confirm="Delete this user?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
