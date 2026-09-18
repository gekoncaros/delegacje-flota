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
  <style>
    .admin-user-card{display:grid;gap:14px}.admin-user-summary{display:flex;justify-content:space-between;gap:12px}.admin-user-summary p{margin:4px 0;color:#64748b}.admin-user-tools{display:flex;align-items:center;gap:8px;flex-wrap:wrap}.admin-user-editor{display:grid;gap:12px;padding-top:12px;border-top:1px solid #e5e7eb}.admin-user-editor input,.admin-user-editor select{box-sizing:border-box;width:100%;padding:11px;border:1px solid #d1d5db;border-radius:10px}.admin-user-editor fieldset{border:1px solid #d1d5db;border-radius:12px}.admin-roles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.admin-role,.admin-user-editor .check-row{display:flex;align-items:center;gap:8px}.admin-role input,.admin-user-editor .check-row input{width:auto}.admin-editor-actions{display:flex;gap:8px;align-items:center}.hidden{display:none!important}@media(max-width:520px){.admin-user-summary{align-items:flex-start;flex-direction:column}.admin-roles{grid-template-columns:1fr}}
  </style>
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
          <input name="displayName" minlength="2" maxlength="190" autocomplete="name" required>
        </label>
        <label>E-mail
          <input type="email" name="email" maxlength="190" autocomplete="email" required>
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

    <section>
      <div class="section-heading"><h2>Ostatnie zdarzenia bezpieczeństwa</h2><button id="refreshAudit" class="mini-action" type="button" aria-label="Odśwież dziennik">↻</button></div>
      <div id="auditList" class="stack"><article class="card empty-card"><div>Ładowanie…</div></article></div>
    </section>
  </main>
</div>
<script src="../assets/js/admin-users.js"></script>
</body>
</html>
