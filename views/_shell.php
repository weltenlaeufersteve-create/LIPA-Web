<!DOCTYPE html>
<html lang="en-GB" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title ?? 'LIPA') ?> — LIPA</title>
  <link rel="stylesheet" href="/assets/css/theme.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php if ($user): ?>
  <div class="app-shell">
    <header class="topbar">
      <button class="hamburger" data-nav-toggle aria-label="Menu">&#9776;</button>
      <span class="sidebar-brand">LIPA</span>
    </header>
    <div class="scrim"></div>
    <aside class="sidebar">
      <div class="sidebar-brand">LIPA</div>
      <nav>
        <a href="/">Dashboard</a>
        <a href="/income">Income</a>
        <a href="/expenses">Expenses</a>
        <a href="/contacts">Contacts</a>
        <?php if (Auth::is('admin','editor')): ?><a href="/projects">Projects</a><?php endif; ?>
        <a href="/reports">Reports</a>
        <?php if (Auth::is('admin')): ?>
          <a href="/categories">Categories</a>
          <a href="/users">Users</a>
          <a href="/settings">Settings</a>
        <?php endif; ?>
        <?php if (Auth::is('admin','viewer')): ?><a href="/activity">Activity log</a><?php endif; ?>
      </nav>
      <form method="post" action="/logout" class="sidebar-logout">
        <span><?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
        <button type="submit" class="btn">Log out</button>
      </form>
    </aside>
    <main class="content"><?= $content ?></main>
  </div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="/assets/js/app.js"></script>
</body>
</html>
