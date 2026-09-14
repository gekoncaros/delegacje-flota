<?php
declare(strict_types=1);
$token = (string)($_GET['token'] ?? '');
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Aktywacja konta — Delegacje + Flota</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <main class="content" style="min-height:100vh;display:grid;place-items:center;padding-top:env(safe-area-inset-top)">
    <section class="form-card" style="width:min(100%,430px)">
      <p class="eyebrow">Delegacje + Flota</p>
      <h1 style="margin-top:0">Aktywuj konto</h1>
      <p class="hint">Ustaw własne hasło. Link zaproszenia jest jednorazowy.</p>
      <div id="inviteError" class="alert error" hidden></div>
      <div id="inviteSuccess" class="notice ok" hidden>Konto zostało aktywowane. Za chwilę otworzymy aplikację.</div>
      <form id="inviteForm" class="form-card" style="box-shadow:none;border:0;padding:0">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
        <label>Hasło
          <input type="password" name="password" minlength="12" autocomplete="new-password" required>
        </label>
        <label>Powtórz hasło
          <input type="password" name="passwordConfirm" minlength="12" autocomplete="new-password" required>
        </label>
        <button id="inviteButton" class="primary-btn dark-btn full" type="submit">Aktywuj konto</button>
      </form>
      <p class="hint" style="margin-bottom:0">Hasło musi mieć minimum 12 znaków i nie jest wysyłane e-mailem ani zapisywane w repozytorium.</p>
    </section>
  </main>
</div>
<script src="./assets/js/invite.js"></script>
</body>
</html>
