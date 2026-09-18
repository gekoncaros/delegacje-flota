<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() === 'production') {
    redirect('./mobile.php');
}

$delegations = array_reverse($_SESSION['demo_delegations']);
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Delegacje</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./">←</a>
    <div><p class="eyebrow">Delegacje</p><h1>Moje delegacje</h1></div>
    <a class="mini-action" href="./new-delegation.php">+</a>
  </header>

  <main class="content">
    <?php if (!$delegations): ?>
      <article class="card empty-card">
        <span class="empty-icon">🧳</span>
        <div><strong>Brak delegacji</strong><p>Utwórz pierwszy wniosek z telefonu.</p></div>
      </article>
    <?php else: ?>
      <div class="stack">
        <?php foreach ($delegations as $delegation): ?>
          <a class="card list-card" href="./delegation.php?id=<?= (int)$delegation['id'] ?>">
            <div>
              <strong><?= h((string)$delegation['destination']) ?></strong>
              <p>
                <?= h((string)($delegation['number'] ?? ('#' . $delegation['id']))) ?>
                · <?= h((string)$delegation['date_from']) ?> → <?= h((string)$delegation['date_to']) ?>
              </p>
            </div>
            <span class="status-pill"><?= h((string)($delegation['trip_status'] ?? $delegation['status'] ?? 'draft')) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <nav class="bottom-nav" aria-label="Nawigacja główna">
    <a href="./"><span>⌂</span><small>Start</small></a>
    <a class="active" href="./delegations.php"><span>🧳</span><small>Delegacje</small></a>
    <a href="./vehicles.php"><span>🚗</span><small>Flota</small></a>
    <a href="#profile"><span>👤</span><small>Profil</small></a>
  </nav>
</div>
</body>
</html>
