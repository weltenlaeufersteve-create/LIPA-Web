<div class="row-between" style="margin-bottom:16px">
  <span class="count"><?= count($rows) ?> scenario<?= count($rows) === 1 ? '' : 's' ?></span>
  <?php if (App\Auth::is('admin','editor')): ?><a class="btn list-new" href="/budget/new">+ New scenario</a><?php endif; ?>
</div>
<div class="card table-card">
  <div class="table-scroll">
  <table class="ledger">
    <thead><tr><th>Scenario</th><th>Project</th><th class="r">Products</th><th class="r">Profit / mo (realistic)</th><th class="r">Break-even</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): $s = $row['s']; $mid = $row['calc']['cases']['mid']; ?>
      <tr>
        <td class="name"><a href="/budget/<?= (int)$s['id'] ?>"><?= e($s['name']) ?></a></td>
        <td><?php if (!empty($s['project_name'])): ?><span class="tag"><?= e($s['project_name']) ?></span><?php endif; ?></td>
        <td class="r muted-cell num"><?= (int)$row['products'] ?></td>
        <td class="r money" style="color:var(--<?= $mid['profit'] >= 0 ? 'pos' : 'neg' ?>)"><?= number_format($mid['profit'], 0) ?></td>
        <td class="r muted-cell"><?= $mid['break_even'] !== null ? number_format($mid['break_even'], 1) . ' mo' : '—' ?></td>
        <td><span class="badge <?= $s['status'] === 'active' ? 'on' : 'off' ?>"><span class="bdot"></span><?= e(ucfirst($s['status'])) ?></span></td>
        <td class="r">
          <?php if (App\Auth::is('admin','editor')): ?>
            <div class="rowact">
              <a class="edit" href="/budget/<?= (int)$s['id'] ?>" aria-label="Open"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></a>
              <form method="post" action="/budget/<?= (int)$s['id'] ?>/delete" style="display:inline" data-confirm="Delete this scenario?">
                <button type="submit" class="del" aria-label="Delete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button>
              </form>
            </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($rows)): ?><tr><td colspan="7" class="muted-cell">No scenarios yet. Create one to plan a production activity.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
