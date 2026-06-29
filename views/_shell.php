<?php use App\Auth; ?>
<!DOCTYPE html>
<html lang="en-GB" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <script>/* apply saved theme before paint to avoid flash */(function(){try{var t=localStorage.getItem('lipa_theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
  <title><?= e($title ?? 'LIPA') ?> — LIPA</title>
  <link rel="icon" type="image/png" href="<?= asset('/assets/icon3.png') ?>">
  <link rel="stylesheet" href="<?= asset('/assets/css/theme.css') ?>">
  <link rel="stylesheet" href="<?= asset('/assets/css/app.css') ?>">
</head>
<body>
<?php if ($user): $org = \App\Models\Setting::all(); $orgName = $org['org_name'] ?? ''; ?>
  <div class="app-shell">
    <header class="topbar">
      <button class="hamburger" data-nav-toggle aria-label="Menu">&#9776;</button>
      <span class="sidebar-brand"><?= e($orgName !== '' ? $orgName : 'LIPA') ?></span>
    </header>
    <button type="button" id="theme-toggle" class="theme-toggle-fixed" aria-label="Toggle theme" title="Toggle theme">🌙</button>
    <div class="scrim"></div>
    <aside class="sidebar">
      <div class="sidebar-brand ngo-brand">
        <?php if (!empty($org['logo'])): ?>
          <img src="/uploads/<?= e($org['logo']) ?>" alt="<?= e($orgName !== '' ? $orgName : 'Logo') ?>" class="ngo-logo">
        <?php else: ?>
          <?= e($orgName !== '' ? $orgName : 'LIPA') ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($org['tax_id']) || !empty($org['ngo_number'])): ?>
        <div class="ngo-ids">
          <?php if (!empty($org['tax_id'])): ?><div>TIN: <?= e($org['tax_id']) ?></div><?php endif; ?>
          <?php if (!empty($org['ngo_number'])): ?><div>No.: <?= e($org['ngo_number']) ?></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <nav class="nav-main">
        <a href="/">Dashboard</a>
        <div class="nav-group">
          <a href="/income">Income</a>
          <a href="/expenses">Expenses</a>
          <a href="/transfers">Transfers</a>
        </div>
        <div class="nav-group">
          <a href="/contacts">Contacts</a>
          <?php if (Auth::is('admin','editor')): ?><a href="/projects">Projects</a><?php endif; ?>
          <a href="/reports">Reports</a>
        </div>
      </nav>
      <div class="sidebar-bottom">
        <?php if (Auth::is('admin','viewer')): ?>
          <nav class="nav-bottom">
            <?php if (Auth::is('admin')): ?><a href="/settings">Settings</a><?php endif; ?>
            <?php if (Auth::is('admin','viewer')): ?><a href="/activity">Activity log</a><?php endif; ?>
          </nav>
        <?php endif; ?>
        <form method="post" action="/logout" class="sidebar-logout">
          <span><?= e($user['name']) ?> (<?= e($user['role']) ?>)</span>
          <button type="submit" class="btn">Log out</button>
        </form>
        <div class="powered-by">Powered by <strong>LIPA</strong> — <span class="powered-tag">Income &amp; Expenses for small NGOs</span></div>
      </div>
    </aside>
    <main class="content"><?= $content ?></main>
  </div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
