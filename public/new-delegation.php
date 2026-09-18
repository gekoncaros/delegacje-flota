<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() === 'production') {
    redirect('./mobile.php?view=new');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $destination = trim((string)($_POST['destination'] ?? ''));
    $purpose = trim((string)($_POST['purpose'] ?? ''));
    $dateFrom = (string)($_POST['date_from'] ?? '');
    $dateTo = (string)($_POST['date_to'] ?? '');
    $transport = (string)($_POST['transport'] ?? 'company_car');

    if ($destination === '') $errors[] = 'Podaj miejsce docelowe.';
    if ($purpose === '') $errors[] = 'Podaj cel delegacji.';
    if ($dateFrom === '' || $dateTo === '') $errors[] = 'Podaj datę rozpoczęcia i zakończenia.';
    if ($dateFrom !== '' && $dateTo !== '' && $dateTo < $dateFrom) {
        $errors[] = 'Data zakończenia nie może być wcześniejsza niż rozpoczęcia.';
    }

    if (!$errors) {
        $nextId = count($_SESSION['demo_delegations']) + 1;
        $_SESSION['demo_delegations'][] = [
            'id' => $nextId,
            'number' => sprintf('DEMO/%s/%04d', date('Y'), $nextId),
            'destination' => $destination,
            'purpose' => $purpose,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'transport' => $transport,
            'approval_status' => 'approved',
            'trip_status' => 'ready',
            'started_at' => null,
            'ended_at' => null,
            'odometer_start' => null,
            'odometer_end' => null,
            'created_at' => date('c'),
        ];

        redirect('./delegation.php?id=' . $nextId);
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Nowa delegacja</title>
  <link rel="manifest" href="./manifest.webmanifest">
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./">←</a>
    <div><p class="eyebrow">Delegacje</p><h1>Nowa delegacja</h1></div>
  </header>

  <main class="content">
    <?php if ($errors): ?>
      <div class="alert error"><?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?></div>
    <?php endif; ?>

    <form class="form-card" method="post">
      <?= csrf_field() ?>

      <label>Cel podróży
        <input name="destination" autocomplete="off" placeholder="np. Gliwice" value="<?= h((string)($_POST['destination'] ?? '')) ?>" required>
      </label>

      <label>Cel delegacji
        <textarea name="purpose" rows="4" placeholder="np. uruchomienie stanowiska, spotkanie z klientem" required><?= h((string)($_POST['purpose'] ?? '')) ?></textarea>
      </label>

      <div class="form-grid">
        <label>Od
          <input type="date" name="date_from" value="<?= h((string)($_POST['date_from'] ?? date('Y-m-d'))) ?>" required>
        </label>
        <label>Do
          <input type="date" name="date_to" value="<?= h((string)($_POST['date_to'] ?? date('Y-m-d'))) ?>" required>
        </label>
      </div>

      <label>Transport
        <select name="transport">
          <option value="company_car">Samochód służbowy</option>
          <option value="private_car">Samochód prywatny</option>
          <option value="train">Pociąg</option>
          <option value="plane">Samolot</option>
          <option value="other">Inny</option>
        </select>
      </label>

      <?php if (app_mode() === 'demo'): ?>
        <p class="hint">Tryb demonstracyjny: wniosek jest automatycznie oznaczany jako gotowy do testów.</p>
      <?php endif; ?>

      <button class="primary-btn dark-btn full" type="submit">Utwórz delegację</button>
    </form>
  </main>
</div>
<script src="./assets/js/pwa.js"></script>
</body>
</html>
