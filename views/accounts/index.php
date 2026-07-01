<?php $activeTab = 'accounts'; include dirname(__DIR__) . '/admin/_tabs.php'; ?>
<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?></span>
  <a class="btn list-new" href="/accounts/new">+ New account</a>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Name</th><th>Type</th><th class="r">Opening balance</th><th class="r">Current balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($accounts as $row): ?>
      <tr>
        <td class="name"><?= e($row['name']) ?></td>
        <td><span class="tag"><?= e(ucfirst($row['type'])) ?></span></td>
        <td class="r money muted-cell"><?= number_format((float)$row['opening_balance'], 2) ?></td>
        <td class="r money"><?= number_format(\App\Models\Account::balance((int)$row['id']), 2) ?></td>
        <td><span class="badge <?= (int)$row['active'] === 1 ? 'on' : 'off' ?>"><span class="bdot"></span><?= (int)$row['active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
        <td class="r">
          <div class="rowact">
            <a class="edit" href="/accounts/<?= (int)$row['id'] ?>/edit" aria-label="Edit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
            <form method="post" action="/accounts/<?= (int)$row['id'] ?>/delete" style="display:inline" data-confirm="Delete this account? Entries keep their history but lose the link.">
              <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
            </form>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($accounts)): ?><tr><td colspan="6" class="muted-cell">No accounts yet.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
