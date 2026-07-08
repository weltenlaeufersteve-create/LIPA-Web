<form method="get" action="/transfers" class="filterbar">
  <div class="field"><label>From</label><input type="date" name="date_from" value="<?= e($f['date_from']) ?>"></div>
  <div class="field"><label>To</label><input type="date" name="date_to" value="<?= e($f['date_to']) ?>"></div>
  <div class="actions">
    <button class="btn" type="submit">Filter</button>
    <a class="btn ghost" href="/transfers">Clear</a>
  </div>
</form>
<div class="row-between" style="margin-bottom:16px">
  <div class="total-chip">Total transferred <b class="num">TZS <?= number_format((float)$total, 2) ?></b></div>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn list-new" href="/transfers/new">+ New transfer</a>
  <?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Date</th><th>From</th><th>To</th><th class="r">Amount (TZS)</th><th>Description</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="muted-cell num"><?= e($row['date']) ?></td>
        <td class="name"><?= e($row['from_name']) ?></td>
        <td class="name"><?= e($row['to_name']) ?></td>
        <td class="r money"><?= number_format((float)$row['amount_tzs'], 2) ?></td>
        <td class="muted-cell"><?= e($row['description']) ?></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/transfers/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/transfers/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this transfer?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="muted-cell">No transfers in this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
