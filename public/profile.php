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
        <a class="action-card" href="./mobile.php"><span>🧳</span><strong>Delegacje</strong><small>Moje wyjazdy</small></a>
        <a class="action-card" href="./fleet.php"><span>🚗</span><strong>Flota</strong><small>Pojazdy</small></a>
        <?php if (has_role($roles, 'manager')): ?>
          <a class="action-card" href="./mobile.php"><span>✓</span><strong>Akceptacje</strong><small>Wnioski pracowników</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'accounting')): ?>
          <a class="action-card" href="./expenses.php"><span>🧾</span><strong>Księgowość</strong><small>Rozliczenia</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'fleet_admin')): ?>
          <a class="action-card" href="./fleet.php"><span>🛠️</span><strong>Admin floty</strong><small>Terminy i pojazdy</small></a>
        <?php endif; ?>
        <?php if (has_role($roles, 'super_admin')): ?>
          <a class="action-card" href="./admin/users.php"><span>🛡️</span><strong>Super Admin</strong><small>Użytkownicy i role</small></a>
        <?php endif; ?>
      </div>
    </section>

    <?php if (app_mode() === 'production'): ?>
    <section class="form-card">
      <h2>Zmień hasło</h2>
      <p class="hint">Użyj co najmniej 12 znaków. Po zmianie identyfikator sesji zostanie odnowiony.</p>
      <div id="passwordMessage" class="alert" hidden></div>
      <form id="passwordForm" class="form-card" style="box-shadow:none;border:0;padding:0">
        <label>Obecne hasło
          <input type="password" name="currentPassword" maxlength="1024" autocomplete="current-password" required>
        </label>
        <label>Nowe hasło
          <input type="password" name="newPassword" minlength="12" maxlength="72" autocomplete="new-password" required>
        </label>
        <label>Powtórz nowe hasło
          <input type="password" name="newPasswordConfirm" minlength="12" maxlength="72" autocomplete="new-password" required>
        </label>
        <button class="primary-btn dark-btn full" type="submit">Zmień hasło</button>
      </form>
    </section>
    <?php endif; ?>

    <section class="card">
      <button id="logoutButton" class="primary-btn dark-btn full" type="button">Wyloguj się</button>
    </section>
  </main>

  <nav class="bottom-nav" aria-label="Nawigacja główna">
    <a href="./"><span>⌂</span><small>Start</small></a>
    <a href="./mobile.php"><span>🧳</span><small>Delegacje</small></a>
    <a href="./fleet.php"><span>🚗</span><small>Flota</small></a>
    <a class="active" href="./profile.php"><span>👤</span><small>Profil</small></a>
  </nav>
</div>
<script>
let csrfToken = '';
async function getSession() {
  if (csrfToken) return csrfToken;
  const response = await fetch('./api/auth/me.php', {credentials:'same-origin'});
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.csrfToken) throw new Error(payload.error || 'Sesja wygasła.');
  csrfToken = payload.csrfToken;
  return csrfToken;
}
document.getElementById('passwordForm')?.addEventListener('submit', async event => {
  event.preventDefault();
  const form = event.currentTarget;
  const message = document.getElementById('passwordMessage');
  const data = new FormData(form);
  const currentPassword = String(data.get('currentPassword') || '');
  const newPassword = String(data.get('newPassword') || '');
  const confirmation = String(data.get('newPasswordConfirm') || '');
  message.hidden = true;
  if (newPassword !== confirmation) {
    message.className = 'alert error'; message.textContent = 'Nowe hasła nie są identyczne.'; message.hidden = false; return;
  }
  const submit = form.querySelector('[type="submit"]');
  submit.disabled = true;
  try {
    const token = await getSession();
    const response = await fetch('./api/auth/change-password.php', {
      method:'POST', credentials:'same-origin',
      headers:{'Content-Type':'application/json','X-CSRF-Token':token},
      body:JSON.stringify({currentPassword,newPassword})
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.ok === false) throw new Error(payload.error || 'Nie udało się zmienić hasła.');
    csrfToken = payload.csrfToken || '';
    form.reset(); message.className = 'alert success'; message.textContent = 'Hasło zostało zmienione.'; message.hidden = false;
  } catch (error) {
    message.className = 'alert error'; message.textContent = error.message || 'Nie udało się zmienić hasła.'; message.hidden = false;
  } finally { submit.disabled = false; }
});
document.getElementById('logoutButton')?.addEventListener('click', async () => {
  try {
    const token = await getSession();
    await fetch('./api/auth/logout.php', {method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':token}});
  } finally { location.replace('./login.php'); }
});
</script>
</body>
</html>
