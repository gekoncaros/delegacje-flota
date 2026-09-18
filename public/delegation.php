<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() === 'production') {
    redirect('./mobile.php');
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$delegation = demo_delegation_by_id($id);

if (!$delegation) {
    http_response_code(404);
    exit('Nie znaleziono delegacji.');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'start') {
        $odometer = filter_input(INPUT_POST, 'odometer', FILTER_VALIDATE_INT);
        if ($odometer === false || $odometer === null || $odometer < 0) {
            $error = 'Podaj prawidłowy stan licznika.';
        } elseif (($delegation['trip_status'] ?? '') !== 'ready') {
            $error = 'Tej delegacji nie można teraz rozpocząć.';
        } else {
            $delegation['trip_status'] = 'started';
            $delegation['started_at'] = date('c');
            $delegation['odometer_start'] = $odometer;
            replace_demo_delegation($delegation);
            redirect('./delegation.php?id=' . $id);
        }
    }

    if ($action === 'finish') {
        $odometer = filter_input(INPUT_POST, 'odometer', FILTER_VALIDATE_INT);
        if ($odometer === false || $odometer === null || $odometer < (int)$delegation['odometer_start']) {
            $error = 'Stan końcowy licznika nie może być niższy od początkowego.';
        } elseif (($delegation['trip_status'] ?? '') !== 'started') {
            $error = 'Ta delegacja nie jest rozpoczęta.';
        } else {
            $delegation['trip_status'] = 'finished';
            $delegation['ended_at'] = date('c');
            $delegation['odometer_end'] = $odometer;
            replace_demo_delegation($delegation);
            redirect('./delegation.php?id=' . $id);
        }
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title><?= h($delegation['number']) ?></title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="./delegations.php">←</a>
    <div><p class="eyebrow"><?= h($delegation['number']) ?></p><h1><?= h($delegation['destination']) ?></h1></div>
  </header>

  <main class="content">
    <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

    <section class="card detail-card">
      <div class="detail-row"><span>Status</span><strong><?= h($delegation['trip_status']) ?></strong></div>
      <div class="detail-row"><span>Termin</span><strong><?= h($delegation['date_from']) ?> → <?= h($delegation['date_to']) ?></strong></div>
      <div class="detail-row"><span>Cel</span><strong><?= h($delegation['purpose']) ?></strong></div>
    </section>

    <?php if ($delegation['trip_status'] === 'ready'): ?>
      <form class="form-card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="start">
        <h2>Rozpocznij delegację</h2>
        <label>Stan licznika
          <input type="number" name="odometer" min="0" inputmode="numeric" placeholder="np. 125320" required>
        </label>
        <button class="primary-btn dark-btn full" type="submit">▶ Rozpocznij delegację</button>
      </form>
    <?php elseif ($delegation['trip_status'] === 'started'): ?>
      <section class="hero-card compact">
        <p class="eyebrow light">Delegacja trwa</p>
        <h2><?= h($delegation['destination']) ?></h2>
        <p>Start: <?= h((string)$delegation['started_at']) ?> · licznik <?= (int)$delegation['odometer_start'] ?> km</p>
      </section>

      <div class="quick-grid">
        <a class="action-card" href="./expense.php?delegation_id=<?= (int)$id ?>"><span>📷</span><strong>Paragon</strong><small>Dodaj dokument</small></a>
        <a class="action-card" href="./vehicles.php"><span>🚗</span><strong>Pojazd</strong><small>Otwórz flotę</small></a>
      </div>

      <form class="form-card" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="action" value="finish">
        <h2>Zakończ delegację</h2>
        <label>Stan licznika końcowy
          <input type="number" name="odometer" min="<?= (int)$delegation['odometer_start'] ?>" inputmode="numeric" required>
        </label>
        <button class="primary-btn dark-btn full" type="submit">■ Zakończ delegację</button>
      </form>
    <?php else: ?>
      <section class="card">
        <strong>Delegacja zakończona</strong>
        <p>Przebieg: <?= max(0, (int)$delegation['odometer_end'] - (int)$delegation['odometer_start']) ?> km</p>
        <a class="primary-btn dark-btn full" href="./expense.php?delegation_id=<?= (int)$id ?>">Dodaj koszt / paragon</a>
      </section>
    <?php endif; ?>
  </main>
</div>
</body>
</html>
