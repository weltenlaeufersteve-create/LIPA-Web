<?php
$tabs = [
  'organisation' => ['/settings', 'Organisation'],
  'accounts'     => ['/accounts', 'Accounts'],
  'categories'   => ['/categories', 'Categories'],
  'users'        => ['/users', 'Users'],
];
$active = $activeTab ?? '';
?>
<nav class="subtabs" role="tablist">
  <?php foreach ($tabs as $key => [$href, $label]): ?>
    <a href="<?= $href ?>" class="subtab<?= $key === $active ? ' active' : '' ?>"><?= $label ?></a>
  <?php endforeach; ?>
</nav>
