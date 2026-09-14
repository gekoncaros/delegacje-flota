<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Support/Env.php';
use Delegacje\Support\Env;
Env::load(dirname(__DIR__) . '/.env');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('delegacje_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    header('Location: ./');
    exit;
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Logowanie — Delegacje + Flota</title>
  <link rel="stylesheet" href="./assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <main class="content" style="min-height:100vh;display:grid;place-items:center;padding-top:env(safe-area-inset-top)">
    <section class="form-card" style="width:min(100%,430px)">
      <p class="eyebrow">Delegacje + Flota</p>
      <h1 style="margin-top:0">Zaloguj się</h1>
      <p class="hint">Użyj firmowego konta aplikacji.</p>
      <div id="loginError" class="alert error" hidden></div>
      <form id="loginForm" class="form-card" style="box-shadow:none;border:0;padding:0">
        <label>E-mail
          <input type="email" name="email" autocomplete="username" required>
        </label>
        <label>Hasło
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button id="loginButton" class="primary-btn dark-btn full" type="submit">Zaloguj</button>
      </form>
      <p class="hint" style="margin-bottom:0">Połączenie wymaga HTTPS. Sesja jest przechowywana w bezpiecznym cookie HttpOnly.</p>
    </section>
  </main>
</div>
<script src="./assets/js/login.js"></script>
</body>
</html>
