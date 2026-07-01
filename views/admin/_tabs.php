<?php
use App\Auth;
// tab => [href, label, roles that may see it]
$allTabs = [
  'organisation' => ['/settings',   'Organisation', ['admin','editor']],
  'accounts'     => ['/accounts',   'Accounts',     ['admin','viewer']],
  'categories'   => ['/categories', 'Categories',   ['admin','viewer']],
  'users'        => ['/users',      'Users',        ['admin']],
];
$active = $activeTab ?? '';
?>
<nav class="subtabs" role="tablist">
  <?php foreach ($allTabs as $key => [$href, $label, $roles]): ?>
    <?php if (Auth::is(...$roles)): ?>
      <a href="<?= $href ?>" class="subtab<?= $key === $active ? ' active' : '' ?>"><?= $label ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>
