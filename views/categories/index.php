<?php $activeTab = 'categories'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Categories</h1>
  <a class="btn btn-primary" href="/categories/new">New category</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Type</th><th>Name</th><th>Sort</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($categories as $row): ?>
    <tr>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= e($row['name']) ?></td>
      <td><?= (int)$row['sort_order'] ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/categories/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/categories/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this category?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
