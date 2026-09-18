<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

if (app_mode() !== 'production') {
    http_response_code(503);
    exit('Panel floty wymaga APP_MODE=production.');
}

$userId = require_production_user();
$roles = $_SESSION['roles'] ?? [];
$csrf = (string) ($_SESSION['csrf_token'] ?? '');
?>
<!doctype html>
<html lang="pl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Flota — Delegacje + Flota</title>
  <link rel="manifest" href="./manifest.webmanifest">
  <link rel="stylesheet" href="./assets/css/app.css">
  <style>
    .fleet-tabs{display:flex;gap:8px;overflow:auto;margin-bottom:14px}.fleet-tabs button{white-space:nowrap;border:0;border-radius:999px;padding:10px 14px;background:#eef2f7}.fleet-tabs button.active{background:#111827;color:#fff}.fleet-list{display:grid;gap:12px}.fleet-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.fleet-actions button{border:0;border-radius:10px;padding:10px 12px;background:#111827;color:#fff}.fleet-actions button.secondary{background:#e5e7eb;color:#111827}.fleet-actions button.danger{background:#fee2e2;color:#991b1b}.fleet-actions select{min-width:150px;padding:9px;border:1px solid #d1d5db;border-radius:10px;background:#fff}.fleet-form{display:grid;gap:12px}.fleet-form input,.fleet-form textarea,.fleet-form select{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d1d5db;border-radius:12px}.fleet-form .check-row{display:flex;align-items:center;gap:8px}.fleet-form .check-row input{width:auto}.hidden{display:none!important}.muted{color:#6b7280}.fleet-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:12px}.fleet-meta div{padding:9px;background:#f8fafc;border-radius:10px}.fleet-meta span{display:block;color:#64748b;font-size:.75rem}.fleet-meta strong{font-size:.9rem;word-break:break-word}@media (max-width:360px){.fleet-meta{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="app-shell">
  <header class="page-header"><a class="back-link" href="./">←</a><div><p class="eyebrow">Flota</p><h1>Pojazdy i rezerwacje</h1></div></header>
  <main class="content">
    <div class="fleet-tabs"><button class="active" id="fleetVehiclesTab" type="button">Pojazdy</button><button id="fleetReservationsTab" type="button">Moje rezerwacje</button><button id="fleetIncidentsTab" type="button">Zgłoszenia</button><?php if (in_array('fleet_admin',$roles,true) || in_array('super_admin',$roles,true)): ?><button id="fleetAddVehicle" type="button">+ Dodaj pojazd</button><?php endif; ?></div>
    <div id="fleetMessage" class="alert" hidden></div>
    <section id="fleetList" class="fleet-list" aria-live="polite"><article class="card">Ładowanie…</article></section>
    <section id="fleetFormArea" class="hidden"></section>
  </main>
  <nav class="bottom-nav" aria-label="Nawigacja główna"><a href="./"><span>⌂</span><small>Start</small></a><a href="./mobile.php"><span>🧳</span><small>Delegacje</small></a><a class="active" href="./fleet.php"><span>🚗</span><small>Flota</small></a><a href="./profile.php"><span>👤</span><small>Profil</small></a></nav>
</div>
<script>window.DELEGACJE_FLEET_BOOT={csrf:<?= json_encode($csrf) ?>,roles:<?= json_encode(array_values($roles)) ?>,userId:<?= (int) $userId ?>};</script>
<script src="./assets/js/fleet-api.js"></script>
<script src="./assets/js/pwa.js"></script>
</body>
</html>
