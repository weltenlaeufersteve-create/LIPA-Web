<div class="page-header" style="display:flex;justify-content:space-between;align-items:center">
  <h1>Projects</h1>
  <a class="btn btn-primary" href="/projects/new">New project</a>
</div>
<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Name</th><th>Code</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($projects as $row): ?>
    <tr>
      <td><?= e($row['name']) ?></td>
      <td><?= e($row['code']) ?></td>
      <td><?= ((int)$row['active'] === 1) ? 'Yes' : 'No' ?></td>
      <td style="text-align:right">
        <a href="/projects/<?= (int)$row['id'] ?>/edit">Edit</a>
        <form method="post" action="/projects/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this project?">
          <button type="submit" class="btn-link-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
