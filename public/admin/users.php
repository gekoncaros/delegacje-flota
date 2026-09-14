<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_bootstrap.php';

use Delegacje\Security\Authorization;

require_production_user();
$user = current_user();
if (!$user || !Authorization::hasRole($user, 'super_admin')) {
    http_response_code(403);
    exit('Brak uprawnień.');
}
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Użytkownicy — Delegacje + Flota</title>
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <header class="page-header">
    <a class="back-link" href="../">←</a>
    <div><p class="eyebrow">Super Admin</p><h1>Użytkownicy</h1></div>
  </header>

  <main class="content">
    <section class="form-card">
      <h2>Nowe zaproszenie</h2>
      <p class="hint">Użytkownik ustawi własne hasło przez jednorazowy link.</p>
      <div id="inviteAdminError" class="alert error" hidden></div>
      <form id="inviteAdminForm" class="form-card" style="box-shadow:none;border:0;padding:0">
        <label>Imię i nazwisko
          <input name="displayName" autocomplete="name" required>
        </label>
        <label>E-mail
          <input type="email" name="email" autocomplete="email" required>
        </label>
        <label>Rola
          <select name="roleCode">
            <option value="employee">Pracownik</option>
            <option value="manager">Przełożony</option>
            <option value="accounting">Księgowość</option>
            <option value="fleet_admin">Administrator floty</option>
            <option value="super_admin">Super Admin</option>
          </select>
        </label>
        <label>Ważność zaproszenia
          <select name="ttlHours">
            <option value="24">24 godziny</option>
            <option value="48" selected>48 godzin</option>
            <option value="72">72 godziny</option>
            <option value="168">7 dni</option>
          </select>
        </label>
        <button class="primary-btn dark-btn full" type="submit">Generuj zaproszenie</button>
      </form>
      <div id="inviteResult" class="notice ok" hidden>
        <strong>Link zaproszenia</strong>
        <textarea id="inviteUrl" rows="4" readonly></textarea>
        <button id="copyInviteButton" class="primary-btn dark-btn full" type="button">Kopiuj link</button>
      </div>
    </section>

    <section>
      <div class="section-heading"><h2>Konta</h2><button id="refreshUsers" class="mini-action" type="button">↻</button></div>
      <div id="usersList" class="stack"><article class="card empty-card"><div>Ładowanie…</div></article></div>
    </section>
  </main>
</div>
<script src="../assets/js/admin-users.js"></script>
</body>
</html>
