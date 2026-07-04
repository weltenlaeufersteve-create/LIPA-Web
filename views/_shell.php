<?php
use App\Auth;
use App\Models\Setting;

$settings = Setting::all();
$accent   = \App\hex_color($settings['accent_color'] ?? null);
$orgName  = $settings['org_name'] ?? '';
$reqPath  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$navActive = static function (string $href) use ($reqPath): string {
    if ($href === '/') { return $reqPath === '/' ? ' active' : ''; }
    return ($reqPath === $href || strpos($reqPath, $href . '/') === 0) ? ' active' : '';
};
// The Settings area spans several routes — highlight "Settings" on any of them.
$settingsActive = preg_match('#^/(settings|accounts|categories|users)#', $reqPath) ? ' active' : '';
// Mobile account chip: role initial + colour.
$roleClass   = $user ? (['admin'=>'role-admin','editor'=>'role-coord','viewer'=>'role-acct'][$user['role']] ?? '') : '';
$roleInitial = $user ? strtoupper(substr(\App\role_label($user['role']), 0, 1)) : '';
// Inline SVG icons (stroke = currentColor)
$ic = [
  'dash'      => '<path d="M3 3h7v9H3zM14 3h7v5h-7zM14 12h7v9h-7zM3 16h7v5H3z"/>',
  'income'    => '<path d="M12 19V5M5 12l7-7 7 7"/>',
  'expense'   => '<path d="M12 5v14M5 12l7 7 7-7"/>',
  'transfer'  => '<path d="M7 10l-3 3 3 3M4 13h9M17 14l3-3-3-3M20 11h-9"/>',
  'contacts'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
  'projects'  => '<path d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  'activities'=> '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M3 17l5-4 4 3 3-2 6 5"/>',
  'reports'   => '<path d="M4 4h16v4H4zM4 12h10v8H4zM18 12h2v8h-2z"/>',
  'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M19 5l-2 2M7 17l-2 2"/>',
  'log'       => '<path d="M12 8v4l3 2"/><circle cx="12" cy="12" r="9"/>',
  'budget'    => '<path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6"/><rect x="12" y="8" width="3" height="10"/><rect x="17" y="4" width="3" height="14"/>',
];
$svg = static function (string $p): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
};
?>
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
  <style>:root{--accent: <?= e($accent) ?>;}</style>
</head>
<body>
<?php if ($user): ?>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-logo">
          <?php if (!empty($settings['logo'])): ?>
            <img src="<?= asset('/uploads/' . $settings['logo']) ?>" alt="<?= e($orgName !== '' ? $orgName : 'Logo') ?>">
          <?php else: ?>
            <?= e(strtoupper(substr($orgName !== '' ? $orgName : 'L', 0, 1))) ?>
          <?php endif; ?>
        </div>
        <div>
          <div class="brand-name"><?= e($orgName !== '' ? $orgName : 'LIPA') ?></div>
          <?php if (!empty($settings['tax_id']) || !empty($settings['ngo_number'])): ?>
            <div class="brand-meta">
              <?php if (!empty($settings['tax_id'])): ?>TIN <?= e($settings['tax_id']) ?><?php endif; ?>
              <?php if (!empty($settings['tax_id']) && !empty($settings['ngo_number'])): ?> &middot; <?php endif; ?>
              <?php if (!empty($settings['ngo_number'])): ?><?= e($settings['ngo_number']) ?><?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <nav class="nav">
        <div class="nav-group">
          <a class="nav-item<?= $navActive('/') ?>" href="/"><?= $svg($ic['dash']) ?>Dashboard</a>
          <a class="nav-item<?= $navActive('/income') ?>" href="/income"><?= $svg($ic['income']) ?>Income</a>
          <a class="nav-item<?= $navActive('/expenses') ?>" href="/expenses"><?= $svg($ic['expense']) ?>Expenses</a>
          <a class="nav-item<?= $navActive('/transfers') ?>" href="/transfers"><?= $svg($ic['transfer']) ?>Transfers</a>
        </div>
        <div class="nav-sep"></div>
        <div class="nav-group">
          <a class="nav-item<?= $navActive('/contacts') ?>" href="/contacts"><?= $svg($ic['contacts']) ?>Contacts</a>
          <?php if (Auth::is('admin','editor')): ?><a class="nav-item<?= $navActive('/projects') ?>" href="/projects"><?= $svg($ic['projects']) ?>Projects</a><?php endif; ?>
          <a class="nav-item<?= $navActive('/activities') ?>" href="/activities"><?= $svg($ic['activities']) ?>Activities</a>
        </div>
        <div class="nav-sep"></div>
        <div class="nav-group">
          <a class="nav-item<?= $navActive('/reports') ?>" href="/reports"><?= $svg($ic['reports']) ?>Reports</a>
          <a class="nav-item<?= $navActive('/budget') ?>" href="/budget"><?= $svg($ic['budget']) ?>Budget</a>
        </div>
      </nav>

      <nav class="nav" style="margin-top:14px">
        <div class="nav-group">
          <?php if (Auth::is('admin','editor')): ?>
            <a class="nav-item<?= $settingsActive ?>" href="/settings"><?= $svg($ic['settings']) ?>Settings</a>
          <?php elseif (Auth::is('viewer')): ?>
            <a class="nav-item<?= $settingsActive ?>" href="/accounts"><?= $svg($ic['settings']) ?>Settings</a>
          <?php endif; ?>
          <?php if (Auth::is('admin','viewer')): ?>
            <a class="nav-item<?= $navActive('/activity') ?>" href="/activity"><?= $svg($ic['log']) ?>Activity log</a>
          <?php endif; ?>
        </div>
      </nav>

      <div class="sidebar-foot">
        <div class="powered">Powered by <b>LIPA</b> <span style="opacity:.75">v<?= \App\VERSION ?></span><br>Income &amp; Expenses for small NGOs</div>
      </div>
    </aside>
    <div class="scrim"></div>

    <div class="main">
      <header class="topbar">
        <div style="display:flex;align-items:center;gap:12px">
          <button class="icon-btn hamburger" data-nav-toggle aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
          <h1 class="page-title"><?= e($title ?? 'Dashboard') ?></h1>
        </div>
        <div class="topbar-right">
          <span class="user-chip acct-desktop" style="padding-left:12px">
            <span class="uname"><?= e($user['name']) ?></span>
            <span class="role"><?= e(\App\role_label($user['role'])) ?></span>
          </span>
          <form method="post" action="/logout" class="acct-desktop" style="margin:0"><button type="submit" class="btn ghost" style="gap:7px"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Log out</button></form>

          <div class="acct-menu">
            <button type="button" class="acct-avatar <?= $roleClass ?>" data-acct-toggle aria-label="Account menu" aria-haspopup="true"><?= e($roleInitial) ?></button>
            <div class="acct-pop" hidden>
              <div class="acct-name"><?= e($user['name']) ?><span><?= e(\App\role_label($user['role'])) ?></span></div>
              <form method="post" action="/logout" style="margin:0"><button type="submit" class="btn ghost" style="width:100%;gap:8px;justify-content:flex-start"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>Log out</button></form>
            </div>
          </div>

          <button type="button" id="help-toggle" class="icon-btn" aria-label="Help" title="How to use LIPA" aria-haspopup="dialog" style="font-family:var(--font-display);font-weight:800;font-size:19px;line-height:1">?</button>
          <button type="button" id="theme-toggle" class="icon-btn" aria-label="Toggle theme" title="Toggle theme">🌙</button>
        </div>
      </header>
      <main class="content"><?= $content ?></main>
    </div>
    <?php include __DIR__ . '/_help.php'; ?>
  </div>
<?php else: ?>
  <?= $content ?>
<?php endif; ?>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
