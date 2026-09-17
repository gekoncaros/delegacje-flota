(() => {
  if (typeof state === 'undefined' || typeof home === 'undefined' || typeof page === 'undefined') return;

  if (!Array.isArray(state.drives)) state.drives = [];
  let qrScanner = null;
  let pendingStart = null;
  let pendingEnd = null;

  const originalHome = home;

  function activeDrive() {
    return state.drives.find(d => d.status === 'active');
  }

  function vehicleById(id) {
    return state.vehicles.find(v => Number(v.id) === Number(id));
  }

  function formatGps(gps) {
    if (!gps) return 'GPS niepobrany';
    return `${gps.lat.toFixed(6)}, ${gps.lng.toFixed(6)} · dokładność ±${Math.round(gps.accuracy || 0)} m`;
  }

  function parseVehicleQr(raw) {
    if (!raw) return null;
    try {
      const url = new URL(raw, location.href);
      if (url.origin !== location.origin || url.pathname !== location.pathname) return null;
      const rawId = url.searchParams.get('vehicle');
      if (!/^\\d+$/.test(rawId || '')) return null;
      const id = Number(rawId);
      if (id) return id;
    } catch (_) {}
    const m = String(raw).match(/(?:vehicle[:=]|pojazd[:=])\s*(\d+)/i);
    return m ? Number(m[1]) : null;
  }

  function scannerFallback(message = '') {
    const el = document.getElementById('qrFallback');
    if (!el) return;
    el.innerHTML = `${message ? `<div class="notice warn">${esc(message)}</div>` : ''}
      <div class="card form">
        <h2>Wybierz pojazd ręcznie</h2>
        <label>Pojazd<select id="manualVehicle">${state.vehicles.map(v => `<option value="${v.id}">${esc(v.make)} ${esc(v.model)} — ${esc(v.reg)}</option>`).join('')}</select></label>
        <button class="btn dark full" onclick="quickStartVehicle(Number(document.getElementById('manualVehicle').value))">Dalej</button>
        <p class="muted">Możesz też zeskanować naklejkę QR zwykłym aparatem telefonu. Link z QR otworzy od razu ekran rozpoczęcia przejazdu.</p>
      </div>`;
  }

  function loadQrLibrary() {
    if (window.Html5Qrcode) return Promise.resolve();
    return Promise.reject(new Error('Skaner QR nie jest dostępny offline. Użyj aparatu telefonu albo wybierz pojazd ręcznie.'));
  }

  async function stopScanner() {
    if (!qrScanner) return;
    try { await qrScanner.stop(); } catch (_) {}
    try { await qrScanner.clear(); } catch (_) {}
    qrScanner = null;
  }

  window.stopQuickScanner = async () => {
    await stopScanner();
    go('home');
  };

  window.openQuickScanner = async () => {
    page('Skanuj QR pojazdu', `
      <div class="quickstart-scan">
        <div class="card">
          <h2>🚗 Zeskanuj kod z auta</h2>
          <p class="muted">Skieruj aparat na kod QR pojazdu. Po rozpoznaniu przejdziesz od razu do GPS i rozpoczęcia przejazdu.</p>
          <div id="qr-reader" class="qr-reader"></div>
          <div id="qrFallback"></div>
          <button class="btn light full" onclick="stopQuickScanner()">Anuluj</button>
        </div>
      </div>`);

    try {
      await loadQrLibrary();
      qrScanner = new Html5Qrcode('qr-reader');
      await qrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 }, aspectRatio: 1 },
        async decodedText => {
          const id = parseVehicleQr(decodedText);
          if (!id) return;
          const vehicle = vehicleById(id);
          if (!vehicle) {
            scannerFallback('Kod QR został odczytany, ale pojazdu nie ma w tej bazie.');
            return;
          }
          await stopScanner();
          window.quickStartVehicle(id);
        },
        () => {}
      );
    } catch (err) {
      scannerFallback(err?.message || 'Aparat lub skaner QR nie jest dostępny.');
    }
  };

  window.quickStartVehicle = async id => {
    await stopScanner();
    const v = vehicleById(id);
    if (!v) return alert('Nie znaleziono pojazdu.');
    if (v.status === 'unsafe' || v.status === 'service') {
      return page('Pojazd niedostępny', `<div class="card"><div class="notice warn">${esc(v.reg)} ma status „${esc(v.status)}”. Rozpoczęcie przejazdu zostało zablokowane.</div><button class="btn light full" onclick="go('vehicle',${v.id})">Otwórz kartę pojazdu</button></div>`);
    }

    pendingStart = { vehicleId: v.id, gps: null };
    const delegations = state.delegations.filter(d => ['approved','started'].includes(d.status));
    page('Wsiadam i ruszam', `
      <div class="card quick-vehicle">
        <div><p class="eyebrow">ROZPOZNANY POJAZD</p><h2>${esc(v.make)} ${esc(v.model)}</h2><p class="muted">${esc(v.reg)} · ${Number(v.mileage || 0).toLocaleString('pl-PL')} km</p></div>
        <span class="pill ok">gotowy</span>
      </div>
      <form class="card form" onsubmit="startVehicleDrive(event,${v.id})">
        <h2>1. Pobierz pozycję GPS</h2>
        <div id="gpsStatus" class="gps-status">📍 Naciśnij przycisk, aby aplikacja pobrała bieżącą pozycję.</div>
        <button type="button" class="btn dark full" onclick="captureStartGps(${v.id})">📍 Pobierz moją pozycję</button>
        <h2>2. Potwierdź przejazd</h2>
        <label>Stan licznika<input name="odometer" type="number" min="${Number(v.mileage || 0)}" value="${Number(v.mileage || 0)}" required></label>
        <label>Rodzaj jazdy<select name="tripType"><option value="business">Służbowa</option><option value="delegation">Delegacja</option><option value="service">Serwis / techniczna</option></select></label>
        <label>Cel / opis<input name="purpose" placeholder="np. klient, serwis, biuro Gliwice"></label>
        ${delegations.length ? `<label>Powiąż z delegacją<select name="delegationId"><option value="">Bez powiązania</option>${delegations.map(d => `<option value="${d.id}">${esc(d.number)} — ${esc(d.destination)}</option>`).join('')}</select></label>` : ''}
        <button id="startDriveBtn" class="btn success full" disabled>▶ RUSZAM</button>
        <small class="muted">GPS jest pobierany tylko po Twoim kliknięciu i zapisuje punkt rozpoczęcia przejazdu.</small>
      </form>`);
  };

  window.captureStartGps = vehicleId => {
    const status = document.getElementById('gpsStatus');
    const btn = document.getElementById('startDriveBtn');
    if (!navigator.geolocation) {
      if (status) status.innerHTML = '❌ Ta przeglądarka nie udostępnia GPS.';
      return;
    }
    if (status) status.innerHTML = '⏳ Pobieram dokładną pozycję GPS…';
    navigator.geolocation.getCurrentPosition(pos => {
      pendingStart = {
        vehicleId,
        gps: {
          lat: pos.coords.latitude,
          lng: pos.coords.longitude,
          accuracy: pos.coords.accuracy,
          timestamp: pos.timestamp || Date.now()
        }
      };
      if (status) status.innerHTML = `✅ ${esc(formatGps(pendingStart.gps))}`;
      if (btn) btn.disabled = false;
    }, err => {
      const msg = err.code === 1 ? 'Nie udzielono zgody na lokalizację.' : err.code === 2 ? 'Nie udało się ustalić pozycji.' : 'Przekroczono czas pobierania GPS.';
      if (status) status.innerHTML = `❌ ${esc(msg)} Włącz lokalizację dla tej strony i spróbuj ponownie.`;
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  };

  window.startVehicleDrive = (e, vehicleId) => {
    e.preventDefault();
    const v = vehicleById(vehicleId);
    if (!v) return;
    if (!pendingStart?.gps || Number(pendingStart.vehicleId) !== Number(vehicleId)) return alert('Najpierw pobierz pozycję GPS.');
    if (activeDrive()) return alert('Masz już aktywny przejazd. Najpierw go zakończ.');

    const f = new FormData(e.target);
    const odometer = Number(f.get('odometer'));
    const delegationId = Number(f.get('delegationId') || 0) || null;
    const drive = {
      id: Date.now(),
      vehicleId: Number(vehicleId),
      employeeId: Number(state.currentUserId || 1),
      employee: typeof employeeName === 'function' ? employeeName(state.currentUserId || 1) : 'Pracownik',
      tripType: f.get('tripType') || 'business',
      purpose: f.get('purpose') || '',
      delegationId,
      odoStart: odometer,
      startGps: pendingStart.gps,
      startedAt: Date.now(),
      status: 'active'
    };
    state.drives.push(drive);
    v.mileage = Math.max(Number(v.mileage || 0), odometer);

    if (delegationId) {
      const d = state.delegations.find(x => Number(x.id) === delegationId);
      if (d && d.status === 'approved') {
        d.status = 'started';
        d.odoStart = odometer;
        d.start = drive.startedAt;
        d.startGps = drive.startGps;
      }
    }

    if (typeof addNotification === 'function') addNotification(`Rozpoczęto przejazd ${v.reg}`);
    save();
    pendingStart = null;
    go('home');
  };

  window.openDriveEnd = driveId => {
    const d = state.drives.find(x => Number(x.id) === Number(driveId) && x.status === 'active');
    if (!d) return go('home');
    const v = vehicleById(d.vehicleId);
    pendingEnd = { driveId: d.id, gps: null };
    page('Zakończ przejazd', `
      <div class="card detail">
        <div><span>Pojazd</span><strong>${esc(v ? `${v.make} ${v.model} · ${v.reg}` : '')}</strong></div>
        <div><span>Start</span><strong>${new Date(d.startedAt).toLocaleString('pl-PL')}</strong></div>
        <div><span>GPS start</span><strong>${esc(formatGps(d.startGps))}</strong></div>
        <div><span>Licznik start</span><strong>${Number(d.odoStart).toLocaleString('pl-PL')} km</strong></div>
      </div>
      <form class="card form" onsubmit="finishVehicleDrive(event,${d.id})">
        <div id="endGpsStatus" class="gps-status">📍 Pobierz pozycję końcową.</div>
        <button type="button" class="btn dark full" onclick="captureEndGps(${d.id})">📍 Pobierz GPS końca</button>
        <label>Stan licznika końcowy<input name="odometer" type="number" min="${d.odoStart}" value="${v?.mileage || d.odoStart}" required></label>
        <button id="finishDriveBtn" class="btn danger full" disabled>■ ZAKOŃCZ PRZEJAZD</button>
      </form>`);
  };

  window.captureEndGps = driveId => {
    const status = document.getElementById('endGpsStatus');
    const btn = document.getElementById('finishDriveBtn');
    if (!navigator.geolocation) return;
    if (status) status.innerHTML = '⏳ Pobieram pozycję końcową…';
    navigator.geolocation.getCurrentPosition(pos => {
      pendingEnd = {
        driveId,
        gps: { lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy, timestamp: pos.timestamp || Date.now() }
      };
      if (status) status.innerHTML = `✅ ${esc(formatGps(pendingEnd.gps))}`;
      if (btn) btn.disabled = false;
    }, err => {
      const msg = err.code === 1 ? 'Brak zgody na lokalizację.' : 'Nie udało się pobrać GPS.';
      if (status) status.innerHTML = `❌ ${esc(msg)}`;
    }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  };

  window.finishVehicleDrive = (e, driveId) => {
    e.preventDefault();
    const d = state.drives.find(x => Number(x.id) === Number(driveId));
    if (!d || !pendingEnd?.gps || Number(pendingEnd.driveId) !== Number(driveId)) return alert('Najpierw pobierz GPS końca.');
    const odoEnd = Number(new FormData(e.target).get('odometer'));
    if (odoEnd < Number(d.odoStart)) return alert('Stan końcowy nie może być niższy od początkowego.');
    d.odoEnd = odoEnd;
    d.endGps = pendingEnd.gps;
    d.endedAt = Date.now();
    d.status = 'finished';
    d.distanceKm = odoEnd - Number(d.odoStart);
    const v = vehicleById(d.vehicleId);
    if (v) v.mileage = Math.max(Number(v.mileage || 0), odoEnd);

    if (d.delegationId) {
      const delegation = state.delegations.find(x => Number(x.id) === Number(d.delegationId));
      if (delegation && delegation.status === 'started') {
        delegation.odoEnd = odoEnd;
        delegation.end = d.endedAt;
        delegation.endGps = d.endGps;
        delegation.status = 'finished';
      }
    }

    if (typeof addNotification === 'function') addNotification(`Zakończono przejazd ${v?.reg || ''} · ${d.distanceKm} km`);
    save();
    pendingEnd = null;
    go('home');
  };

  home = function() {
    originalHome();
    const main = document.querySelector('main');
    if (!main) return;
    const active = activeDrive();
    const card = active ? (() => {
      const v = vehicleById(active.vehicleId);
      return `<section class="quickstart-wrap"><div class="card quickstart-card active"><div><p class="eyebrow">AKTYWNY PRZEJAZD</p><h2>🚗 ${esc(v?.reg || 'Pojazd')}</h2><p>${esc(active.purpose || 'Przejazd służbowy')}</p><p class="muted">Start: ${new Date(active.startedAt).toLocaleString('pl-PL')} · ${Number(active.odoStart).toLocaleString('pl-PL')} km</p></div><button class="btn danger" onclick="openDriveEnd(${active.id})">■ Zakończ</button></div></section>`;
    })() : `<section class="quickstart-wrap"><button class="quickstart-hero" onclick="openQuickScanner()"><span class="quickstart-icon">▣</span><span><strong>Skanuj QR i ruszaj</strong><small>Auto → GPS → licznik → start</small></span><b>›</b></button></section>`;
    const hero = main.querySelector('.hero');
    if (hero) hero.insertAdjacentHTML('afterend', card); else main.insertAdjacentHTML('afterbegin', card);
  };

  window.showDriveHistory = () => {
    const rows = [...state.drives].reverse();
    page('Historia przejazdów', rows.length ? `<div class="stack">${rows.map(d => { const v=vehicleById(d.vehicleId); return `<div class="card"><div class="list"><div><strong>${esc(v?.reg || 'Pojazd')}</strong><p class="muted">${new Date(d.startedAt).toLocaleString('pl-PL')} · ${esc(d.purpose || d.tripType)}</p></div><span class="pill ${d.status==='finished'?'ok':'warn'}">${esc(d.status)}</span></div><p>${d.status==='finished' ? `${Number(d.distanceKm || 0)} km` : 'Przejazd trwa'}</p></div>`; }).join('')}</div>` : '<div class="card empty"><div class="ico">🚗</div><div><strong>Brak przejazdów</strong></div></div>');
  };

  const params = new URLSearchParams(location.search);
  const scannedVehicle = Number(params.get('vehicle'));
  if (scannedVehicle && vehicleById(scannedVehicle)) {
    history.replaceState({}, '', location.pathname);
    setTimeout(() => window.quickStartVehicle(scannedVehicle), 0);
  } else {
    render();
  }
})();