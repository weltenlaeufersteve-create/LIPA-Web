<h1>Contacts</h1>
<div class="list-toolbar">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn" href="/contacts">All</a>
    <a class="btn" href="/contacts?type=donor">Donors</a>
    <a class="btn" href="/contacts?type=vendor">Vendors</a>
  </div>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn btn-primary list-new" href="/contacts/new">New contact</a>
  <?php endif; ?>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Type</th><th>Email</th><th>Phone</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($contacts as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e(ucfirst($row['type'])) ?></td>
      <td><?= e($row['email']) ?></td>
      <td><?= e($row['phone']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <?php if (App\Auth::is('admin','editor')): ?>
          <a href="/contacts/<?= (int)$row['id'] ?>/edit">Edit</a>
          <form method="post" action="/contacts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this contact?">
            <button type="submit" class="btn-link-danger">Delete</button>
          </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
