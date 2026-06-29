<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Transfers</h1>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary" href="/transfers/new">New transfer</a>
  <?php endif; ?>
</div>
<form method="get" action="/transfers" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:18px">
  <label style="margin:0">From <input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></label>
  <label style="margin:0">To <input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></label>
  <button class="btn" type="submit">Filter</button>
  <a class="btn" href="/transfers">Clear</a>
</form>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Date</th><th>From</th><th>To</th><th>Amount (TZS)</th><th>Description</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $row): ?>
    <tr>
      <td><?= e($row['date']) ?></td>
      <td><?= e($row['from_name']) ?></td>
      <td><?= e($row['to_name']) ?></td>
      <td><?= number_format((float)$row['amount_tzs'], 2) ?></td>
      <td><?= e($row['description']) ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/transfers/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/transfers/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this transfer?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
