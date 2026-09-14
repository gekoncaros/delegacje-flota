<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

$delegationId = (int)($_GET['delegation_id'] ?? $_POST['delegation_id'] ?? 0);
$delegation = $delegationId ? demo_delegation_by_id($delegationId) : null;
$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $category = trim((string)($_POST['category'] ?? 'other'));
    $description = trim((string)($_POST['description'] ?? ''));
    $storedPath = null;

    if ($amount === false || $amount === null || $amount <= 0) {
        $errors[] = 'Podaj prawidłową kwotę.';
    }

    if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Nie udało się przesłać dokumentu.';
        } elseif ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'Plik jest większy niż 10 MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/pdf' => 'pdf',
            ];

            if (!isset($allowed[$mime])) {
                $errors[] = 'Dozwolone są pliki JPG, PNG i PDF.';
            } else {
                $uploadDir = dirname(__DIR__) . '/uploads';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0750, true);
                }

                $safeName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                $target = $uploadDir . '/' . $safeName;

                if (!is_dir($uploadDir) || !is_writable($uploadDir) || !move_uploaded_file($file['tmp_name'], $target)) {
                    $errors[] = 'Serwer nie może zapisać pliku. Sprawdź uprawnienia katalogu uploads.';
                } else {
                    $storedPath = 'uploads/' . $safeName;
                }
            }
        }
    }

    if (!$errors) {
        $_SESSION['demo_expenses'][] = [
            'id' => count($_SESSION['demo_expenses']) + 1,
            'delegation_id' => $delegationId ?: null,
            'category' => $category,
            'amount' => (float)$amount,
            'currency' => 'PLN',
            'description' => $description,
            'document_path' => $storedPath,
            'created_at' => date('c'),
        ];
        $saved = true;
    }
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Dodaj wydatek</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="<?= $delegation ? './delegation.php?id=' . (int)$delegation['id'] : './' ?>">←</a>
    <div><p class="eyebrow">Koszty</p><h1>Dodaj wydatek</h1></div>
  </header>

  <main class="content">
    <?php if ($saved): ?><div class="alert success">Wydatek został zapisany w trybie demonstracyjnym.</div><?php endif; ?>
    <?php if ($errors): ?><div class="alert error"><?php foreach ($errors as $error): ?><div><?= h($error) ?></div><?php endforeach; ?></div><?php endif; ?>

    <form class="form-card" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="delegation_id" value="<?= (int)$delegationId ?>">

      <label>Kategoria
        <select name="category">
          <option value="fuel">Paliwo</option>
          <option value="hotel">Nocleg</option>
          <option value="parking">Parking</option>
          <option value="toll">Opłata drogowa</option>
          <option value="meal">Posiłek</option>
          <option value="other">Inne</option>
        </select>
      </label>

      <label>Kwota PLN
        <input type="number" name="amount" min="0.01" step="0.01" inputmode="decimal" required>
      </label>

      <label>Opis
        <textarea name="description" rows="3" placeholder="Opcjonalny opis kosztu"></textarea>
      </label>

      <label class="file-drop">📷 Zdjęcie paragonu / dokument
        <input type="file" name="document" accept="image/jpeg,image/png,application/pdf" capture="environment">
        <small>JPG, PNG lub PDF, maks. 10 MB</small>
      </label>

      <button class="primary-btn dark-btn full" type="submit">Zapisz wydatek</button>
    </form>
  </main>
</div>
</body>
</html>
