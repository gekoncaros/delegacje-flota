<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$userId = require_production_user();
$user = current_user();
$roles = $user['roles'] ?? [];

function has_role(array $roles, string $role): bool
{
    return in_array($role, $roles, true);
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Profil — Delegacje + Flota</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./">←</a>
    <div><p class="eyebrow">Konto</p><h1>Profil</h1></div>
  </header>

  <main class="content">
    <section class="card">
      <p class="eyebrow">Zalogowany użytkownik</p>
      <h2><?= h((string)($user['display_name'] ?? 'Użytkownik')) ?></h2>
      <p><?= h((string)($user['email'] ?? '')) ?></p>
      <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:12px">
        <?php foreach ($roles as $role): ?>
          <span class="status-pill"><?= h((string)$role) ?></span>
        <?php endforeach; ?>
      </div>
    </section>

    <section>
      <div class="section-heading"><h2>Moje moduły</h2></div>
      <div class="quick-grid">
        <a class="action-card" href="./delegations.php"><span>🧳</span><strong>Delegacje</strong><small>Moje wyjazdy</small></a>
        <a class="action-card" href="./vehicles.php"><span>🚗</span><strong>Flota</strong><small>Pojazdy</small></a>
        <?php if (has_role($roles, 'manager')): ?>
          <a class="action-card" href="./delegations.php"><span>✓</span><strong>Akceptacje</strong><small>Wnioski pracowników</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'accounting')): ?>
          <a class="action-card" href="#accounting"><span>🧾</span><strong>Księgowość</strong><small>Rozliczenia</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'fleet_admin')): ?>
          <a class="action-card" href="./vehicles.php"><span>🛠️</span><strong>Admin floty</strong><small>Terminy i pojazdy</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'super_admin')): ?>
          <a class="action-card" href="./admin/users.php"><span>🛡️</span><strong>Super Admin</strong><small>Użytkownicy i role</small></a>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <button id="logoutButton" class="primary-btn dark-btn full" type="button">Wyloguj się</button>
    </section>
  </main>

  <nav class="bottom-nav" aria-label="Nawigacja główna">
    <a href="./"><span>⌂</span><small>Start</small></a>
    <a href="./delegations.php"><span>🧳</span><small>Delegacje</small></a>
    <a href="./vehicles.php"><span>🚗</span><small>Flota</small></a>
    <a class="active" href="./profile.php"><span>👤</span><small>Profil</small></a>
  </nav>
</div>
<script>
document.getElementById('logoutButton')?.addEventListener('click', async () => {
  const me = await fetch('./api/auth/me.php', {credentials:'same-origin'}).then(r => r.json()).catch(() => ({}));
  if (!me.csrfToken) return;
  await fetch('./api/auth/logout.php', {
    method:'POST', credentials:'same-origin', headers:{'X-CSRF-Token':me.csrfToken}
  });
  location.replace('./login.php');
});
</script>
</body>
</html>
