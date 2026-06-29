<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Accounts</h1>
  <a class="btn btn-primary" href="/accounts/new">New account</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Type</th><th>Opening balance (TZS)</th><th>Current balance (TZS)</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($accounts as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= number_format((float)$row['opening_balance'], 2) ?></td>
      <td><?= number_format(\App\Models\Account::balance((int)$row['id']), 2) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/accounts/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/accounts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this account? Entries keep their history but lose the link.">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
