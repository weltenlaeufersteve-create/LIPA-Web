<?php $org = \App\Models\Setting::all(); $orgName = $org['org_name'] ?? ''; ?>
<div class="login-wrap" style="max-width:360px;margin:10vh auto;text-align:center">
  <?php if (!empty($org['logo'])): ?>
    <img src="/uploads/<?= e($org['logo']) ?>" alt="<?= e($orgName !== '' ? $orgName : 'Logo') ?>" class="login-logo">
  <?php else: ?>
    <h1 class="login-org"><?= e($orgName !== '' ? $orgName : 'LIPA') ?></h1>
  <?php endif; ?>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error" style="text-align:left"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/login" style="text-align:left">
    <label>Email <input type="email" name="email" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
  <p class="login-powered">Powered by <strong>LIPA</strong><br>Income &amp; Expenses for small NGOs</p>
</div>
