<div class="login-wrap" style="max-width:360px;margin:10vh auto">
  <h1>LIPA</h1>
  <p class="login-tagline">Income &amp; Expenses for small NGOs</p>
  <?php if (!empty($error)): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
  <?php endif; ?>
  <form method="post" action="/login">
    <label>Email <input type="email" name="email" required autofocus></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn btn-primary">Sign in</button>
  </form>
</div>
