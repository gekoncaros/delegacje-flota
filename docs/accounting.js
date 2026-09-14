(() => {
  if (typeof state === 'undefined' || typeof go === 'undefined') return;

  const moneyFmt = new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' });
  const SETTINGS_KEY = 'delegacjeAccountingSettingsV1';
  let accountingSettings = JSON.parse(localStorage.getItem(SETTINGS_KEY) || 'null') || {
    dailyAllowance: 45,
    overnightRate: 67.5,
    mileageRate: 1.15
  };

  if (!state.settlements) state.settlements = [];

  function saveAccountingSettings() {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(accountingSettings));
  }

  function settlementFor(id) {
    return state.settlements.find(x => Number(x.delegationId) === Number(id));
  }

  function linkedExpenses(id) {
    return state.expenses.filter(x => Number(x.delegationId) === Number(id));
  }

  function calcSettlement(d, values = {}) {
    const odoKm = Math.max(0, Number(d.odoEnd || 0) - Number(d.odoStart || 0));
    const days = Math.max(0, Number(values.days ?? 1));
    const nights = Math.max(0, Number(values.nights ?? 0));
    const privateCar = values.privateCar === true || values.privateCar === 'on';
    const dietRate = Number(values.dailyAllowance ?? accountingSettings.dailyAllowance);
    const nightRate = Number(values.overnightRate ?? accountingSettings.overnightRate);
    const kmRate = Number(values.mileageRate ?? accountingSettings.mileageRate);
    const expenses = linkedExpenses(d.id).reduce((sum, x) => sum + Number(x.amount || 0), 0);
    const diet = days * dietRate;
    const overnight = nights * nightRate;
    const mileage = privateCar ? odoKm * kmRate : 0;
    return { odoKm, days, nights, expenses, diet, overnight, mileage, total: expenses + diet + overnight + mileage };
  }

  function statusPill(status) {
    const map = { draft: 'robocze', submitted: 'do księgowości', booked: 'zaksięgowane', paid: 'wypłacone' };
    const cls = status === 'paid' || status === 'booked' ? 'ok' : status === 'submitted' ? 'warn' : '';
    return `<span class="pill ${cls}">${map[status] || status || 'robocze'}</span>`;
  }

  function accountingDashboard() {
    const finished = state.delegations.filter(d => d.status === 'finished');
    const settlements = finished.map(d => ({ d, s: settlementFor(d.id) }));
    const submitted = settlements.filter(x => x.s?.status === 'submitted').length;
    const booked = settlements.filter(x => ['booked', 'paid'].includes(x.s?.status)).length;
    const total = settlements.reduce((sum, x) => sum + Number(x.s?.total || 0), 0);

    page('Panel księgowości', `
      <div class="stats accounting-stats">
        <div class="stat"><strong>${finished.length}</strong><small>zakończone</small></div>
        <div class="stat"><strong>${submitted}</strong><small>do obsługi</small></div>
        <div class="stat"><strong>${booked}</strong><small>zaksięgowane</small></div>
        <div class="stat"><strong>${moneyFmt.format(total)}</strong><small>rozliczenia</small></div>
      </div>

      <div class="card accounting-settings">
        <div class="section-title"><h2>Stawki testowe</h2></div>
        <form class="form" onsubmit="saveAccountingRates(event)">
          <div class="row2"><label>Dieta / dzień<input name="dailyAllowance" type="number" min="0" step="0.01" value="${accountingSettings.dailyAllowance}"></label><label>Nocleg / noc<input name="overnightRate" type="number" min="0" step="0.01" value="${accountingSettings.overnightRate}"></label></div>
          <label>Kilometrówka / km<input name="mileageRate" type="number" min="0" step="0.01" value="${accountingSettings.mileageRate}"></label>
          <small class="muted">Stawki są konfigurowalne i służą obecnie do testów aplikacji.</small>
          <button class="btn dark full">Zapisz stawki</button>
        </form>
      </div>

      <div class="toolbar accounting-toolbar"><button class="btn dark" onclick="exportSettlementsCsv()">Eksport CSV</button><button class="btn light" onclick="window.print()">Drukuj zestawienie</button></div>

      <div class="stack">${settlements.length ? settlements.map(({d,s}) => {
        const estimate = s || calcSettlement(d);
        return `<div class="card accounting-row" onclick="openSettlement(${d.id})"><div class="list"><div><strong>${esc(d.number)} · ${esc(d.destination)}</strong><p class="muted">${esc(d.employee || 'Pracownik')} · ${esc(d.from)} → ${esc(d.to)}</p></div>${statusPill(s?.status)}</div><div class="accounting-total">${moneyFmt.format(Number(estimate.total || 0))}</div></div>`;
      }).join('') : '<div class="card empty"><div class="ico">🧾</div><div><strong>Brak zakończonych delegacji</strong><p>Zakończ delegację, aby pojawiła się w księgowości.</p></div></div>'}</div>
    `);
  }

  function settlementPage(id) {
    const d = state.delegations.find(x => Number(x.id) === Number(id));
    if (!d) return accountingDashboard();
    const existing = settlementFor(id) || {};
    const calc = calcSettlement(d, existing);
    const expenses = linkedExpenses(id);

    page(`Rozliczenie ${d.number}`, `
      <div class="card detail">
        <div><span>Pracownik</span><strong>${esc(d.employee || 'Jan Kowalski')}</strong></div>
        <div><span>Trasa / cel</span><strong>${esc(d.destination)}</strong></div>
        <div><span>Termin</span><strong>${esc(d.from)} → ${esc(d.to)}</strong></div>
        <div><span>Przebieg</span><strong>${calc.odoKm} km</strong></div>
      </div>

      <form class="card form" onsubmit="saveSettlement(event,${d.id})">
        <div class="row2"><label>Liczba dni diety<input name="days" type="number" min="0" step="0.5" value="${existing.days ?? 1}"></label><label>Liczba noclegów<input name="nights" type="number" min="0" value="${existing.nights ?? 0}"></label></div>
        <label class="checkline"><input name="privateCar" type="checkbox" ${existing.privateCar ? 'checked' : ''}> Rozlicz kilometrówkę samochodu prywatnego</label>
        <div class="row2"><label>Dieta / dzień<input name="dailyAllowance" type="number" min="0" step="0.01" value="${existing.dailyAllowance ?? accountingSettings.dailyAllowance}"></label><label>Stawka noclegu<input name="overnightRate" type="number" min="0" step="0.01" value="${existing.overnightRate ?? accountingSettings.overnightRate}"></label></div>
        <label>Stawka za km<input name="mileageRate" type="number" min="0" step="0.01" value="${existing.mileageRate ?? accountingSettings.mileageRate}"></label>
        <label>Status<select name="status"><option value="draft" ${existing.status==='draft'?'selected':''}>Robocze</option><option value="submitted" ${existing.status==='submitted'?'selected':''}>Do księgowości</option><option value="booked" ${existing.status==='booked'?'selected':''}>Zaksięgowane</option><option value="paid" ${existing.status==='paid'?'selected':''}>Wypłacone</option></select></label>
        <label>Uwagi księgowości<textarea name="note" rows="3">${esc(existing.note || '')}</textarea></label>
        <button class="btn dark full">Przelicz i zapisz</button>
      </form>

      <div class="card settlement-summary">
        <h2>Podsumowanie</h2>
        <div class="detail"><div><span>Udokumentowane wydatki</span><strong>${moneyFmt.format(calc.expenses)}</strong></div><div><span>Diety</span><strong>${moneyFmt.format(calc.diet)}</strong></div><div><span>Noclegi</span><strong>${moneyFmt.format(calc.overnight)}</strong></div><div><span>Kilometrówka</span><strong>${moneyFmt.format(calc.mileage)}</strong></div><div class="total-row"><span>Razem</span><strong>${moneyFmt.format(calc.total)}</strong></div></div>
      </div>

      <div class="card"><h2>Dokumenty i wydatki</h2>${expenses.length ? `<div class="stack">${expenses.map(x=>`<div class="list"><div><strong>${esc(x.cat || 'Wydatek')}</strong><p class="muted">${esc(x.desc || '')}</p></div><strong>${moneyFmt.format(Number(x.amount||0))}</strong></div>`).join('')}</div>` : '<p class="muted">Brak wydatków przypisanych do tej delegacji.</p>'}</div>
      <div class="toolbar"><button class="btn dark" onclick="printSettlement(${d.id})">PDF / drukuj</button><button class="btn light" onclick="accountingDashboard()">Wróć do księgowości</button></div>
    `);
  }

  window.saveAccountingRates = e => {
    e.preventDefault(); const f = new FormData(e.target);
    accountingSettings = { dailyAllowance:Number(f.get('dailyAllowance')), overnightRate:Number(f.get('overnightRate')), mileageRate:Number(f.get('mileageRate')) };
    saveAccountingSettings(); accountingDashboard();
  };

  window.openSettlement = id => settlementPage(id);
  window.accountingDashboard = accountingDashboard;

  window.saveSettlement = (e,id) => {
    e.preventDefault(); const f = new FormData(e.target); const d = state.delegations.find(x=>Number(x.id)===Number(id));
    const values = { days:Number(f.get('days')), nights:Number(f.get('nights')), privateCar:f.get('privateCar')==='on', dailyAllowance:Number(f.get('dailyAllowance')), overnightRate:Number(f.get('overnightRate')), mileageRate:Number(f.get('mileageRate')), status:f.get('status'), note:f.get('note') || '' };
    const totals = calcSettlement(d, values); const idx = state.settlements.findIndex(x=>Number(x.delegationId)===Number(id));
    const row = { delegationId:id, ...values, ...totals, updatedAt:Date.now() };
    if (idx >= 0) state.settlements[idx] = row; else state.settlements.push(row);
    addNotification(`Rozliczenie ${d.number}: ${values.status}`); save(); settlementPage(id);
  };

  window.exportSettlementsCsv = () => {
    const header = ['Numer','Pracownik','Cel','Od','Do','Status','Diety','Noclegi','Kilometrowka','Wydatki','Razem'];
    const rows = state.delegations.filter(d=>d.status==='finished').map(d=>{ const s=settlementFor(d.id)||calcSettlement(d); return [d.number,d.employee||'Pracownik',d.destination,d.from,d.to,s.status||'draft',s.diet||0,s.overnight||0,s.mileage||0,s.expenses||0,s.total||0]; });
    const csv = [header,...rows].map(r=>r.map(v=>`"${String(v??'').replaceAll('"','""')}"`).join(';')).join('\n');
    const blob = new Blob(['\ufeff'+csv],{type:'text/csv;charset=utf-8'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`delegacje-rozliczenia-${new Date().toISOString().slice(0,10)}.csv`; a.click(); setTimeout(()=>URL.revokeObjectURL(a.href),1000);
  };

  window.printSettlement = id => { settlementPage(id); setTimeout(()=>window.print(),80); };

  const originalProfile = profile;
  profile = function() {
    originalProfile();
    const main = document.querySelector('main');
    if (main) main.insertAdjacentHTML('beforeend', `<div class="card accounting-entry"><strong>🧾 Księgowość</strong><p class="muted">Diety, noclegi, kilometrówka, statusy i eksport zbiorczy.</p><button class="btn dark full" onclick="accountingDashboard()">Otwórz panel księgowości</button></div>`);
  };
})();
