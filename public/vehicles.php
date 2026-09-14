<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$vehicles = $_SESSION['demo_vehicles'];
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Flota</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./">←</a>
    <div><p class="eyebrow">Flota</p><h1>Pojazdy</h1></div>
  </header>

  <main class="content">
    <div class="stack">
      <?php foreach ($vehicles as $vehicle): ?>
        <article class="card vehicle-card">
          <div class="vehicle-icon">🚗</div>
          <div class="vehicle-main">
            <strong><?= h($vehicle['make'] . ' ' . $vehicle['model']) ?></strong>
            <p><?= h($vehicle['registration_number']) ?> · <?= number_format((int)$vehicle['mileage_km'], 0, ',', ' ') ?> km</p>
            <span class="status-pill"><?= h($vehicle['status']) ?></span>
          </div>
          <div class="vehicle-actions">
            <a href="./expense.php">⛽ Tankowanie</a>
            <a href="./incident.php?vehicle_id=<?= (int)$vehicle['id'] ?>">⚠️ Problem</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </main>

  <nav class="bottom-nav" aria-label="Nawigacja główna">
    <a href="./"><span>⌂</span><small>Start</small></a>
    <a href="./delegations.php"><span>🧳</span><small>Delegacje</small></a>
    <a class="active" href="./vehicles.php"><span>🚗</span><small>Flota</small></a>
    <a href="#profile"><span>👤</span><small>Profil</small></a>
  </nav>
</div>
</body>
</html>
