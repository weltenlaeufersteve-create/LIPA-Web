<?php $action = '/income'; include dirname(__DIR__) . '/_filters.php'; ?>
<div class="row-between" style="margin-bottom:16px">
  <div class="total-chip">Total received <b class="num">TZS <?= number_format($total, 2) ?></b></div>
  <?php if (App\Auth::is('admin','editor')): ?>
    <a class="btn list-new" href="/income/new">+ New income</a>
  <?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Date</th><th>Donor</th><th>Category</th><th>Account</th><th class="r">Amount (TZS)</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="muted-cell num"><?= e($row['date']) ?></td>
        <td class="name"><?= e($row['contact_name']) ?></td>
        <td><?php if (!empty($row['category_name'])): ?><span class="tag"><?= e($row['category_name']) ?></span><?php endif; ?></td>
        <td class="muted-cell"><?= e($row['account_name']) ?></td>
        <td class="r money" style="color:var(--pos)"><?= number_format((float)$row['amount_tzs'], 2) ?></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/income/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/income/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this entry?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="6" class="muted-cell">No income in this period.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
