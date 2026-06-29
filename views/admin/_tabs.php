<?php
$tabs = [
  'organisation' => ['/settings', 'Organisation'],
  'accounts'     => ['/accounts', 'Accounts'],
  'categories'   => ['/categories', 'Categories'],
  'users'        => ['/users', 'Users'],
];
$active = $activeTab ?? '';
?>
<nav class="admin-tabs" style="display:flex;gap:6px;flex-wrap:wrap;border-bottom:1px solid var(--border);margin-bottom:18px">
  <?php foreach ($tabs as $key => [$href, $label]): ?>
    <a href="<?= $href ?>" class="admin-tab<?= $key === $active ? ' is-active' : '' ?>"
       style="padding:8px 14px;text-decoration:none;border-radius:8px 8px 0 0;<?= $key === $active ? 'background:var(--accent);color:var(--accent-text);font-weight:600' : 'color:var(--text-secondary)' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</nav>
