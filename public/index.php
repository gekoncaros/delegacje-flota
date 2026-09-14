<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() === 'production') {
    $userId = require_production_user();
    $user = current_user();
    $userName = (string)($user['display_name'] ?? 'Użytkownik');
    $recentDelegations = production_services()['delegations']->recentForUser($userId, 20);
    $currentTrip = null;
    foreach ($recentDelegations as $delegation) {
        $status = (string)($delegation['trip_status'] ?? $delegation['status'] ?? '');
        if ($status === 'started') {
            $currentTrip = $delegation;
            break;
        }
    }
    $recentDelegations = array_slice($recentDelegations, 0, 3);
} else {
    $userName = $_SESSION['user_name'] ?? 'Użytkownik demo';
    $currentTrip = current_demo_delegation();
    $recentDelegations = array_slice(array_reverse($_SESSION['demo_delegations']), 0, 3);
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="Delegacje">
  <title>Delegacje + Flota</title>
  <link rel="manifest" href="./manifest.webmanifest">
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div>
        <p class="eyebrow">Delegacje + Flota</p>
        <h1>Dzień dobry, <?= h((string)$userName) ?></h1>
      </div>
      <div style="display:flex;gap:8px">
        <button class="icon-btn" type="button" aria-label="Powiadomienia">🔔</button>
        <?php if (app_mode() === 'production'): ?>
          <button class="icon-btn" id="logoutButton" type="button" aria-label="Wyloguj">↪</button>
        <?php endif; ?>
      </div>
    </header>

    <main class="content">
      <section class="hero-card">
        <div>
          <p class="eyebrow light">Szybki start</p>
          <h2>Nowa delegacja</h2>
          <p>Utwórz wniosek i przejdź cały mobilny proces od startu do rozliczenia.</p>
        </div>
        <a class="primary-btn light-btn" href="./new-delegation.php">+ Nowa delegacja</a>
      </section>

      <section>
        <div class="section-heading"><h2>Aktualna delegacja</h2></div>
        <?php if ($currentTrip): ?>
          <a class="card active-trip" href="./delegation.php?id=<?= (int)$currentTrip['id'] ?>">
            <div>
              <span class="status-pill">w trasie</span>
              <h3><?= h((string)$currentTrip['destination']) ?></h3>
              <p><?= h((string)($currentTrip['number'] ?? ('#' . $currentTrip['id']))) ?></p>
            </div>
            <span class="chevron">›</span>
          </a>
        <?php else: ?>
          <article class="card empty-card">
            <span class="empty-icon">🧳</span>
            <div><strong>Brak aktywnej delegacji</strong><p>Rozpoczęty wyjazd pojawi się tutaj automatycznie.</p></div>
          </article>
        <?php endif; ?>
      </section>

      <section>
        <div class="section-heading"><h2>Szybkie akcje</h2></div>
        <div class="quick-grid">
          <a class="action-card" href="./expense.php"><span>📷</span><strong>Paragon</strong><small>Zrób zdjęcie</small></a>
          <a class="action-card" href="./expense.php"><span>⛽</span><strong>Tankowanie</strong><small>Dodaj koszt</small></a>
          <a class="action-card" href="./expense.php"><span>🧾</span><strong>Wydatek</strong><small>Dodaj dokument</small></a>
          <a class="action-card" href="./vehicles.php"><span>🚗</span><strong>Pojazd</strong><small>Moja flota</small></a>
          <a class="action-card" href="./vehicles.php"><span>⚠️</span><strong>Problem</strong><small>Zgłoś usterkę</small></a>
          <button class="action-card install-card" id="installPwaButton" type="button" hidden><span>📲</span><strong>Zainstaluj</strong><small>Dodaj aplikację</small></button>
        </div>
      </section>

      <section>
        <div class="section-heading">
          <h2>Ostatnie delegacje</h2>
          <a href="./delegations.php">Pokaż wszystkie</a>
        </div>

        <?php if (!$recentDelegations): ?>
          <article class="card empty-card">
            <span class="empty-icon">📋</span>
            <div><strong>Brak danych do wyświetlenia</strong><p>Utwórz pierwszą delegację.</p></div>
          </article>
        <?php else: ?>
          <div class="stack">
            <?php foreach ($recentDelegations as $delegation): ?>
              <a class="card list-card" href="./delegation.php?id=<?= (int)$delegation['id'] ?>">
                <div><strong><?= h((string)$delegation['destination']) ?></strong><p><?= h((string)($delegation['number'] ?? ('#' . $delegation['id']))) ?></p></div>
                <span class="status-pill"><?= h((string)($delegation['trip_status'] ?? $delegation['status'] ?? '')) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </main>

    <nav class="bottom-nav" aria-label="Nawigacja główna">
      <a class="active" href="./"><span>⌂</span><small>Start</small></a>
      <a href="./delegations.php"><span>🧳</span><small>Delegacje</small></a>
      <a href="./vehicles.php"><span>🚗</span><small>Flota</small></a>
      <a href="#profile"><span>👤</span><small>Profil</small></a>
    </nav>
  </div>

  <script src="./assets/js/pwa.js"></script>
  <script>
    const installButton = document.getElementById('installPwaButton');
    window.addEventListener('pwa-install-available', () => {
      if (installButton) installButton.hidden = false;
    });
    installButton?.addEventListener('click', async () => {
      await window.installDelegacjePwa();
      installButton.hidden = true;
    });

    document.getElementById('logoutButton')?.addEventListener('click', async () => {
      const me = await fetch('./api/auth/me.php', {credentials:'same-origin'}).then(r => r.json()).catch(() => ({}));
      await fetch('./api/auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: me.csrfToken ? {'X-CSRF-Token': me.csrfToken} : {}
      });
      location.replace('./login.php');
    });
  </script>
</body>
</html>
