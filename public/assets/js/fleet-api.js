(() => {
  const boot = window.DELEGACJE_FLEET_BOOT || { csrf: '', userId: 0, roles: [] };
  const list = document.getElementById('fleetList');
  const formArea = document.getElementById('fleetFormArea');
  const message = document.getElementById('fleetMessage');
  const tabs = {
    vehicles: document.getElementById('fleetVehiclesTab'),
    reservations: document.getElementById('fleetReservationsTab'),
    incidents: document.getElementById('fleetIncidentsTab')
  };
  let vehicles = [];
  let reservations = [];
  let incidents = [];
  let delegations = [];
  let managementUsers = [];
  let mode = 'vehicles';
  const roles = new Set(boot.roles || []);
  const canManage = roles.has('fleet_admin') || roles.has('super_admin');

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[char]));
  const dateTime = value => value ? new Intl.DateTimeFormat('pl-PL', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(value).replace(' ', 'T'))) : '—';
  const state = value => ({ available:'dostępny', service:'serwis', unsafe:'niedostępny', active:'aktywna', cancelled:'anulowana', open:'nowe', in_progress:'w realizacji', resolved:'zamknięte' }[value] || value || '—');

  async function api(url, options = {}) {
    const headers = { Accept: 'application/json', ...(options.headers || {}) };
    if (options.method && options.method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = boot.csrf;
    }
    const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });
    const payload = await response.json().catch(() => ({ ok:false, error:'Nieprawidłowa odpowiedź serwera.' }));
    if (!response.ok || payload.ok === false) throw new Error(payload.error || 'Operacja nie powiodła się.');
    return payload;
  }

  function showMessage(text, kind = 'success') {
    message.textContent = text;
    message.className = `alert ${kind}`;
    message.hidden = false;
  }

  function clearForm() {
    formArea.replaceChildren();
    formArea.classList.add('hidden');
  }

  async function refresh() {
    list.innerHTML = '<article class="card">Ładowanie…</article>';
    clearForm();
    try {
      const requests = [
        api('./api/vehicles/list.php'),
        api('./api/vehicles/reservations-list.php'),
        api('./api/vehicles/incidents-list.php'),
        api('./api/delegations/list.php')
      ];
      if (canManage) requests.push(api('./api/vehicles/management-options.php'));
      const results = await Promise.all(requests);
      vehicles = results[0].items || [];
      reservations = results[1].items || [];
      incidents = results[2].items || [];
      delegations = (results[3].items || []).filter(item => Number(item.user_id) === Number(boot.userId) && !['rejected','finished'].includes(item.status));
      managementUsers = results[4]?.users || [];
      render();
    } catch (error) {
      list.innerHTML = `<article class="card"><strong>Nie można pobrać floty</strong><p>${esc(error.message)}</p></article>`;
    }
  }

  function render() {
    Object.entries(tabs).forEach(([key, button]) => button?.classList.toggle('active', key === mode));
    if (mode === 'vehicles') {
      list.innerHTML = vehicles.length ? vehicles.map(vehicle => {
        const enabled = vehicle.status === 'available';
        return `<article class="card"><strong>${esc(`${vehicle.make} ${vehicle.model}`)}</strong><p class="muted">${esc(vehicle.registration_number)}${vehicle.assigned_user_name ? ` · ${esc(vehicle.assigned_user_name)}` : ''}</p><div class="fleet-meta"><div><span>Przebieg</span><strong>${Number(vehicle.mileage_km || 0).toLocaleString('pl-PL')} km</strong></div><div><span>Status</span><strong>${esc(state(vehicle.status))}</strong></div><div><span>Badanie</span><strong>${esc(vehicle.inspection_due || 'brak danych')}</strong></div><div><span>OC</span><strong>${esc(vehicle.insurance_due || 'brak danych')}</strong></div></div><div class="fleet-actions"><button type="button" data-reserve="${Number(vehicle.id)}" ${enabled ? '' : 'disabled'}>Zarezerwuj</button><button type="button" class="secondary" data-report="${Number(vehicle.id)}">Zgłoś problem</button>${canManage ? `<button type="button" class="secondary" data-edit-vehicle="${Number(vehicle.id)}">Edytuj</button>` : ''}</div></article>`;
      }).join('') : '<article class="card"><strong>Brak pojazdów</strong><p>Administrator floty nie dodał jeszcze pojazdów.</p></article>';
      list.querySelectorAll('[data-reserve]').forEach(button => button.addEventListener('click', () => showReservationForm(Number(button.dataset.reserve))));
      list.querySelectorAll('[data-report]').forEach(button => button.addEventListener('click', () => showIncidentForm(Number(button.dataset.report))));
      list.querySelectorAll('[data-edit-vehicle]').forEach(button => button.addEventListener('click', () => showVehicleForm(selectedVehicle(Number(button.dataset.editVehicle)))));
      return;
    }
    if (mode === 'reservations') {
      list.innerHTML = reservations.length ? reservations.map(item => `<article class="card"><strong>${esc(`${item.make} ${item.model}`)}</strong><p class="muted">${esc(item.registration_number)} · ${esc(item.purpose || 'bez opisu')}</p><div class="fleet-meta"><div><span>Od</span><strong>${esc(dateTime(item.starts_at))}</strong></div><div><span>Do</span><strong>${esc(dateTime(item.ends_at))}</strong></div>${item.delegation_number ? `<div><span>Delegacja</span><strong>${esc(item.delegation_number)} · ${esc(item.delegation_destination)}</strong></div>` : ''}</div><div class="fleet-actions"><span class="status-pill">${esc(state(item.status))}</span>${item.status === 'active' ? `<button type="button" class="danger" data-cancel="${Number(item.id)}">Anuluj</button>` : ''}</div></article>`).join('') : '<article class="card"><strong>Brak rezerwacji</strong><p>Twoje rezerwacje pojawią się tutaj.</p></article>';
      list.querySelectorAll('[data-cancel]').forEach(button => button.addEventListener('click', () => cancelReservation(Number(button.dataset.cancel))));
      return;
    }
    list.innerHTML = incidents.length ? incidents.map(item => {
      const assignees = managementUsers.map(user => `<option value="${Number(user.id)}" ${Number(item.assigned_to_user_id) === Number(user.id) ? 'selected' : ''}>${esc(user.display_name)}</option>`).join('');
      const adminActions = canManage ? `<div class="fleet-actions">${item.status !== 'resolved' ? `<select aria-label="Osoba odpowiedzialna" data-incident-assignee="${Number(item.id)}"><option value="">Wybierz osobę</option>${assignees}</select><button type="button" data-incident-action="assign" data-id="${Number(item.id)}">Przypisz</button>` : ''}${item.status === 'open' ? `<button type="button" data-incident-action="start" data-id="${Number(item.id)}">Rozpocznij</button>` : ''}${item.status !== 'resolved' ? `<button type="button" data-incident-action="resolve" data-id="${Number(item.id)}">Zamknij</button>` : `<button type="button" data-incident-action="reopen" data-id="${Number(item.id)}">Otwórz ponownie</button>`}</div>` : '';
      return `<article class="card"><strong>${esc(`${item.make} ${item.model}`)}</strong><p class="muted">${esc(item.registration_number)} · ${esc(state(item.status))}</p><p>${esc(item.description)}</p><div class="fleet-meta"><div><span>Kategoria</span><strong>${esc(item.category)}</strong></div><div><span>Zgłoszono</span><strong>${esc(dateTime(item.created_at))}</strong></div><div><span>Odpowiedzialny</span><strong>${esc(item.assigned_name || 'nieprzypisany')}</strong></div><div><span>Bezpieczny do jazdy</span><strong>${Number(item.unsafe_to_drive) ? 'nie' : 'tak'}</strong></div></div>${item.resolution_note ? `<p><strong>Rozwiązanie:</strong> ${esc(item.resolution_note)}</p>` : ''}${adminActions}</article>`;
    }).join('') : '<article class="card"><strong>Brak zgłoszeń</strong><p>Możesz zgłosić problem przy wybranym pojeździe.</p></article>';
    list.querySelectorAll('[data-incident-action]').forEach(button => button.addEventListener('click', () => transitionIncident(Number(button.dataset.id), button.dataset.incidentAction)));
  }

  function selectedVehicle(vehicleId) {
    return vehicles.find(vehicle => Number(vehicle.id) === vehicleId);
  }

  function showReservationForm(vehicleId) {
    const vehicle = selectedVehicle(vehicleId); if (!vehicle) return;
    const delegationOptions = delegations.map(item => `<option value="${Number(item.id)}">${esc(item.number)} · ${esc(item.destination)}</option>`).join('');
    formArea.innerHTML = `<form id="reservationForm" class="card fleet-form"><div class="section-heading"><h2>Rezerwacja</h2><button type="button" class="mini-action" id="closeFleetForm">×</button></div><p>${esc(`${vehicle.make} ${vehicle.model} · ${vehicle.registration_number}`)}</p><label>Delegacja<select name="delegation_id"><option value="">Bez powiązania</option>${delegationOptions}</select></label><label>Od<input name="starts_at" type="datetime-local" required></label><label>Do<input name="ends_at" type="datetime-local" required></label><label>Cel / uwagi<input name="purpose" maxlength="255" placeholder="Opcjonalnie"></label><button class="primary-btn dark-btn full" type="submit">Zapisz rezerwację</button></form>`;
    formArea.classList.remove('hidden');
    document.getElementById('closeFleetForm').onclick = clearForm;
    document.getElementById('reservationForm').addEventListener('submit', async event => {
      event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget).entries()); data.vehicle_id = vehicleId;
      try { await api('./api/vehicles/reservations-create.php', { method:'POST', body:JSON.stringify(data) }); showMessage('Rezerwacja została zapisana.'); await refresh(); mode = 'reservations'; render(); }
      catch (error) { showMessage(error.message, 'error'); }
    });
  }

  function showIncidentForm(vehicleId) {
    const vehicle = selectedVehicle(vehicleId); if (!vehicle) return;
    formArea.innerHTML = `<form id="incidentForm" class="card fleet-form"><div class="section-heading"><h2>Zgłoś problem</h2><button type="button" class="mini-action" id="closeFleetForm">×</button></div><p>${esc(`${vehicle.make} ${vehicle.model} · ${vehicle.registration_number}`)}</p><label>Kategoria<select name="category"><option value="failure">Awaria</option><option value="damage">Szkoda</option><option value="tires">Opony</option><option value="warning">Kontrolka</option><option value="service">Serwis</option><option value="documents">Dokumenty</option><option value="other">Inne</option></select></label><label>Opis<textarea name="description" rows="5" minlength="10" maxlength="4000" required placeholder="Opisz problem i okoliczności"></textarea></label><label>Stan licznika<input name="mileage_km" type="number" min="0" inputmode="numeric" value="${Number(vehicle.mileage_km || 0)}"></label><label class="check-row"><input type="checkbox" name="unsafe_to_drive" value="1"><span>Pojazd nie powinien dalej jechać</span></label><button class="primary-btn dark-btn full" type="submit">Wyślij zgłoszenie</button></form>`;
    formArea.classList.remove('hidden');
    document.getElementById('closeFleetForm').onclick = clearForm;
    document.getElementById('incidentForm').addEventListener('submit', async event => {
      event.preventDefault(); const data = Object.fromEntries(new FormData(event.currentTarget).entries()); data.vehicle_id = vehicleId; data.unsafe_to_drive = Boolean(data.unsafe_to_drive);
      try { await api('./api/vehicles/incidents-create.php', { method:'POST', body:JSON.stringify(data) }); showMessage('Zgłoszenie zostało wysłane.'); await refresh(); mode = 'incidents'; render(); }
      catch (error) { showMessage(error.message, 'error'); }
    });
  }

  function showVehicleForm(vehicle = null) {
    if (!canManage) return;
    const value = (key, fallback = '') => esc(vehicle?.[key] ?? fallback);
    const selected = (key, expected) => String(vehicle?.[key] ?? '') === expected ? 'selected' : '';
    const userOptions = managementUsers.map(user => `<option value="${Number(user.id)}" ${Number(vehicle?.assigned_user_id) === Number(user.id) ? 'selected' : ''}>${esc(user.display_name)} · ${esc(user.email)}</option>`).join('');
    formArea.innerHTML = `<form id="vehicleAdminForm" class="card fleet-form"><div class="section-heading"><h2>${vehicle ? 'Edytuj pojazd' : 'Nowy pojazd'}</h2><button type="button" class="mini-action" id="closeFleetForm">×</button></div><input type="hidden" name="id" value="${vehicle ? Number(vehicle.id) : ''}"><div class="form-grid"><label>Marka<input name="make" maxlength="100" value="${value('make')}" required></label><label>Model<input name="model" maxlength="100" value="${value('model')}" required></label></div><label>Numer rejestracyjny<input name="registration_number" maxlength="32" value="${value('registration_number')}" required></label><label>VIN<input name="vin" minlength="17" maxlength="17" value="${value('vin')}" autocomplete="off"></label><div class="form-grid"><label>Rok produkcji<input name="year" type="number" min="1950" max="${new Date().getFullYear()+1}" value="${value('year')}"></label><label>Przebieg km<input name="mileage_km" type="number" min="0" value="${value('mileage_km',0)}" required></label></div><div class="form-grid"><label>Paliwo<select name="fuel_type"><option value="petrol" ${selected('fuel_type','petrol')}>Benzyna</option><option value="diesel" ${selected('fuel_type','diesel')}>Diesel</option><option value="lpg" ${selected('fuel_type','lpg')}>LPG</option><option value="hybrid" ${selected('fuel_type','hybrid')}>Hybryda</option><option value="electric" ${selected('fuel_type','electric')}>Elektryczny</option><option value="other" ${selected('fuel_type','other')}>Inne</option></select></label><label>Status<select name="status"><option value="available" ${selected('status','available')}>Dostępny</option><option value="service" ${selected('status','service')}>Serwis</option><option value="unsafe" ${selected('status','unsafe')}>Niedostępny</option></select></label></div><label>Przypisany użytkownik<select name="assigned_user_id"><option value="">Brak przypisania</option>${userOptions}</select></label><div class="form-grid"><label>Badanie techniczne do<input name="inspection_due" type="date" value="${value('inspection_due')}"></label><label>Ubezpieczenie do<input name="insurance_due" type="date" value="${value('insurance_due')}"></label></div><label>Uwagi<textarea name="notes" rows="4" maxlength="4000">${value('notes')}</textarea></label><button class="primary-btn dark-btn full" type="submit">Zapisz pojazd</button></form>`;
    formArea.classList.remove('hidden');
    document.getElementById('closeFleetForm').onclick = clearForm;
    document.getElementById('vehicleAdminForm').addEventListener('submit', async event => {
      event.preventDefault();
      const data = Object.fromEntries(new FormData(event.currentTarget).entries());
      try { await api('./api/vehicles/save.php', { method:'POST', body:JSON.stringify(data) }); showMessage(vehicle ? 'Pojazd został zaktualizowany.' : 'Pojazd został dodany.'); await refresh(); mode='vehicles'; render(); }
      catch (error) { showMessage(error.message, 'error'); }
    });
    formArea.scrollIntoView({behavior:'smooth', block:'start'});
  }

  async function cancelReservation(id) {
    if (!window.confirm('Anulować tę rezerwację?')) return;
    try { await api('./api/vehicles/reservations-cancel.php', { method:'POST', body:JSON.stringify({ id }) }); showMessage('Rezerwacja została anulowana.'); await refresh(); mode = 'reservations'; render(); }
    catch (error) { showMessage(error.message, 'error'); }
  }

  async function transitionIncident(id, action) {
    const assignee = list.querySelector(`[data-incident-assignee="${id}"]`)?.value || null;
    if (action === 'assign' && !assignee) { showMessage('Wybierz osobę odpowiedzialną.', 'error'); return; }
    const note = action === 'resolve' ? window.prompt('Opis wykonanej naprawy:', '') : '';
    if (action === 'resolve' && note === null) return;
    try {
      await api('./api/vehicles/incidents-transition.php', { method:'POST', body:JSON.stringify({ id, action, assigned_to_user_id:assignee, note }) });
      showMessage('Status zgłoszenia został zaktualizowany.');
      await refresh(); mode='incidents'; render();
    } catch (error) { showMessage(error.message, 'error'); }
  }

  tabs.vehicles?.addEventListener('click', () => { mode = 'vehicles'; clearForm(); render(); });
  tabs.reservations?.addEventListener('click', () => { mode = 'reservations'; clearForm(); render(); });
  tabs.incidents?.addEventListener('click', () => { mode = 'incidents'; clearForm(); render(); });
  document.getElementById('fleetAddVehicle')?.addEventListener('click', () => showVehicleForm());
  refresh();
})();
