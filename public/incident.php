<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() === 'production') {
    redirect('./fleet.php');
}

$vehicleId = (int)($_GET['vehicle_id'] ?? $_POST['vehicle_id'] ?? 0);
$vehicle = null;
foreach ($_SESSION['demo_vehicles'] as $candidate) {
    if ((int)$candidate['id'] === $vehicleId) {
        $vehicle = $candidate;
        break;
    }
}

$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION['demo_incidents'][] = [
        'vehicle_id' => $vehicleId,
        'category' => (string)($_POST['category'] ?? 'other'),
        'description' => trim((string)($_POST['description'] ?? '')),
        'unsafe_to_drive' => isset($_POST['unsafe_to_drive']),
        'created_at' => date('c'),
    ];
    $saved = true;
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Zgłoś problem</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./vehicles.php">←</a>
    <div><p class="eyebrow">Flota</p><h1>Zgłoś problem</h1></div>
  </header>

  <main class="content">
    <?php if ($saved): ?><div class="alert success">Zgłoszenie zostało zapisane w trybie demonstracyjnym.</div><?php endif; ?>

    <form class="form-card" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="vehicle_id" value="<?= (int)$vehicleId ?>">

      <?php if ($vehicle): ?><p class="hint"><?= h($vehicle['make'] . ' ' . $vehicle['model'] . ' · ' . $vehicle['registration_number']) ?></p><?php endif; ?>

      <label>Kategoria
        <select name="category">
          <option value="failure">Awaria</option>
          <option value="damage">Szkoda</option>
          <option value="tires">Opony</option>
          <option value="warning">Kontrolka</option>
          <option value="service">Serwis</option>
          <option value="documents">Dokumenty</option>
          <option value="other">Inne</option>
        </select>
      </label>

      <label>Opis
        <textarea name="description" rows="5" required placeholder="Opisz problem i okoliczności"></textarea>
      </label>

      <label class="check-row">
        <input type="checkbox" name="unsafe_to_drive" value="1">
        <span>Pojazd nie powinien dalej jechać</span>
      </label>

      <button class="primary-btn dark-btn full" type="submit">Wyślij zgłoszenie</button>
    </form>
  </main>
</div>
</body>
</html>
