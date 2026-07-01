<div class="row-between" style="margin-bottom:16px">
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="btn ghost" href="/contacts">All</a>
    <a class="btn ghost" href="/contacts?type=donor">Donors</a>
    <a class="btn ghost" href="/contacts?type=vendor">Vendors</a>
  </div>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn list-new" href="/contacts/new">+ New contact</a>
  <?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Name</th><th>Type</th><th>Email</th><th>Phone</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($contacts as $row): ?>
      <tr>
        <td class="name"><?= e($row['name']) ?></td>
        <td><span class="tag"><?= e(ucfirst($row['type'])) ?></span></td>
        <td class="muted-cell"><?= e($row['email']) ?></td>
        <td class="muted-cell"><?= e($row['phone']) ?></td>
        <td><span class="badge <?= (int)$row['active'] === 1 ? 'on' : 'off' ?>"><span class="bdot"></span><?= (int)$row['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/contacts/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/contacts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this contact?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($contacts)): ?><tr><td colspan="6" class="muted-cell">No contacts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
