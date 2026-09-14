<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

if (app_mode() !== 'production') {
    http_response_code(503);
    exit('Mobilna aplikacja produkcyjna wymaga APP_MODE=production.');
}

$userId = require_production_user();
$userName = (string)($_SESSION['user_name'] ?? 'Użytkownik');
$roles = $_SESSION['roles'] ?? [];
$csrf = (string)($_SESSION['csrf_token'] ?? '');
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Delegacje">
  <title>Delegacje + Flota</title>
  <link rel="manifest" href="./manifest.webmanifest">
  <link rel="stylesheet" href="./assets/css/app.css">
  <style>
    .mobile-tabs{display:flex;gap:8px;overflow:auto;margin-bottom:14px}.mobile-tabs button{white-space:nowrap;border:0;border-radius:999px;padding:10px 14px;background:#eef2f7}.mobile-tabs button.active{background:#111827;color:#fff}.mobile-form{display:grid;gap:12px}.mobile-form input,.mobile-form textarea,.mobile-form select{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d1d5db;border-radius:12px}.mobile-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.mobile-actions button{border:0;border-radius:10px;padding:10px 12px}.mobile-actions .primary{background:#111827;color:#fff}.mobile-actions .danger{background:#fee2e2;color:#991b1b}.mobile-status{font-size:12px;font-weight:700;text-transform:uppercase}.mobile-card{cursor:pointer}.muted{color:#6b7280}.hidden{display:none!important}
  </style>
</head>
<body>
<div class="app-shell">
  <header class="topbar"><div><p class="eyebrow">Delegacje + Flota</p><h1><?= h($userName) ?></h1></div><a class="icon-btn" href="./profile.php" aria-label="Profil">👤</a></header>
  <main class="content">
    <div class="mobile-tabs"><button id="tabList" class="active">Delegacje</button><button id="tabNew">+ Nowa</button><?php if (in_array('manager',$roles,true) || in_array('super_admin',$roles,true)): ?><button id="tabApprovals">Akceptacje</button><?php endif; ?><?php if (in_array('accounting',$roles,true) || in_array('super_admin',$roles,true)): ?><button id="tabAccounting">Księgowość</button><?php endif; ?></div>
    <section id="viewList"><div id="delegationsList" class="stack"><article class="card">Ładowanie…</article></div></section>
    <section id="viewNew" class="hidden"><form id="newDelegationForm" class="card mobile-form"><label>Miejsce docelowe<input name="destination" required></label><label>Cel delegacji<textarea name="purpose" rows="4" required></textarea></label><div class="form-grid"><label>Od<input type="date" name="date_from" required></label><label>Do<input type="date" name="date_to" required></label></div><label>Transport<select name="transport"><option value="company_car">Samochód służbowy</option><option value="private_car">Samochód prywatny</option><option value="train">Pociąg</option><option value="plane">Samolot</option><option value="other">Inny</option></select></label><button class="primary-btn dark-btn full">Wyślij delegację</button></form></section>
    <section id="detail" class="hidden"></section>
  </main>
</div>
<script>window.DELEGACJE_BOOT={csrf:<?= json_encode($csrf) ?>,roles:<?= json_encode(array_values($roles)) ?>};</script>
<script src="./assets/js/mobile-api.js"></script>
<script src="./assets/js/pwa.js"></script>
</body>
</html>
