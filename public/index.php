<?php
declare(strict_types=1);

session_start();

$userName = $_SESSION['user_name'] ?? 'Użytkownik';
$currentTrip = null;
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
        <h1>Dzień dobry, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></h1>
      </div>
      <button class="icon-btn" type="button" aria-label="Powiadomienia">🔔</button>
    </header>

    <main class="content">
      <section class="hero-card">
        <div>
          <p class="eyebrow light">Szybki start</p>
          <h2>Nowa delegacja</h2>
          <p>Utwórz wniosek i wyślij go do akceptacji bezpośrednio z telefonu.</p>
        </div>
        <a class="primary-btn light-btn" href="#new-delegation">+ Nowa delegacja</a>
      </section>

      <section>
        <div class="section-heading">
          <h2>Aktualna delegacja</h2>
        </div>

        <?php if ($currentTrip): ?>
          <article class="card">
            <strong><?= htmlspecialchars($currentTrip['route'], ENT_QUOTES, 'UTF-8') ?></strong>
          </article>
        <?php else: ?>
          <article class="card empty-card">
            <span class="empty-icon">🧳</span>
            <div>
              <strong>Brak aktywnej delegacji</strong>
              <p>Po zaakceptowaniu wyjazdu pojawi się tutaj możliwość rozpoczęcia podróży.</p>
            </div>
          </article>
        <?php endif; ?>
      </section>

      <section>
        <div class="section-heading">
          <h2>Szybkie akcje</h2>
        </div>
        <div class="quick-grid">
          <button class="action-card" type="button"><span>📷</span><strong>Paragon</strong><small>Zrób zdjęcie</small></button>
          <button class="action-card" type="button"><span>⛽</span><strong>Tankowanie</strong><small>Dodaj koszt</small></button>
          <button class="action-card" type="button"><span>🧾</span><strong>Wydatek</strong><small>Dodaj dokument</small></button>
          <button class="action-card" type="button"><span>🚗</span><strong>Pojazd</strong><small>Moja flota</small></button>
          <button class="action-card" type="button"><span>⚠️</span><strong>Problem</strong><small>Zgłoś usterkę</small></button>
          <button class="action-card install-card" id="installPwaButton" type="button" hidden><span>📲</span><strong>Zainstaluj</strong><small>Dodaj aplikację</small></button>
        </div>
      </section>

      <section>
        <div class="section-heading">
          <h2>Ostatnie delegacje</h2>
          <a href="#delegations">Pokaż wszystkie</a>
        </div>
        <article class="card empty-card">
          <span class="empty-icon">📋</span>
          <div>
            <strong>Brak danych do wyświetlenia</strong>
            <p>Historia delegacji pojawi się po integracji z istniejącą bazą systemu.</p>
          </div>
        </article>
      </section>
    </main>

    <nav class="bottom-nav" aria-label="Nawigacja główna">
      <a class="active" href="./"><span>⌂</span><small>Start</small></a>
      <a href="#delegations"><span>🧳</span><small>Delegacje</small></a>
      <a href="#fleet"><span>🚗</span><small>Flota</small></a>
      <a href="#profile"><span>👤</span><small>Profil</small></a>
    </nav>
  </div>

  <script src="./assets/js/pwa.js"></script>
  <script>
    const installButton = document.getElementById('installPwaButton');
    window.addEventListener('pwa-install-available', () => {
      installButton.hidden = false;
    });
    installButton?.addEventListener('click', async () => {
      await window.installDelegacjePwa();
      installButton.hidden = true;
    });
  </script>
</body>
</html>
