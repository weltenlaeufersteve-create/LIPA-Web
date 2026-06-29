<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Expenses</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/expenses/new">New expense</a>
  <?php endif; ?>
</div>
<?php $action = '/expenses'; include dirname(__DIR__) . '/_filters.php'; ?>
<p><strong>Total (TZS): <?= number_format($total, 2) ?></strong></p>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>Vendor</th><th>Category</th><th>Project</th><th>Description</th><th>Amount (TZS)</th><th>Receipt</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['contact_name']) ?></td>
      <td><?= e($row['category_name']) ?></td>
      <td><?= e($row['project_name']) ?></td>
      <td><?= e($row['description']) ?></td>
      <td><?= number_format((float)$row['amount_tzs'], 2) ?></td>
      <td><?php if (!empty($row['receipt_path'])): ?><a href="/expenses/<?= (int)$row['id'] ?>/receipt">View</a><?php endif; ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/expenses/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/expenses/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this entry?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
