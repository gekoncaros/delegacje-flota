(() => {
  const boot = window.DELEGACJE_BOOT || {csrf:'',roles:[]};
  const list = document.getElementById('delegationsList');
  const detail = document.getElementById('detail');
  const viewList = document.getElementById('viewList');
  const viewNew = document.getElementById('viewNew');
  const form = document.getElementById('newDelegationForm');
  let items = [];

  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const roles = new Set(boot.roles || []);

  async function api(url, options={}) {
    const headers = {'Accept':'application/json', ...(options.headers||{})};
    if (options.method && options.method !== 'GET') {
      headers['Content-Type'] = 'application/json';
      headers['X-CSRF-Token'] = boot.csrf;
    }
    const res = await fetch(url, {...options, headers, credentials:'same-origin'});
    const data = await res.json().catch(()=>({ok:false,error:'Nieprawidłowa odpowiedź serwera.'}));
    if (!res.ok || data.ok === false) throw new Error(data.error || 'Błąd operacji.');
    return data;
  }

  function show(name) {
    viewList.classList.toggle('hidden', name!=='list');
    viewNew.classList.toggle('hidden', name!=='new');
    detail.classList.add('hidden');
    document.querySelectorAll('.mobile-tabs button').forEach(b=>b.classList.remove('active'));
  }

  function statusLabel(s) {
    return ({pending:'oczekuje',approved:'zatwierdzona',rejected:'odrzucona',started:'w trasie',finished:'zakończona'}[s] || s);
  }

  function renderRows(mode='all') {
    let rows = items;
    if (mode==='approvals') rows = items.filter(x=>x.status==='pending');
    if (mode==='accounting') rows = items.filter(x=>x.status==='finished' || x.accounting_status!=='not_ready');
    list.innerHTML = rows.length ? rows.map(x=>`<article class="card list-card mobile-card" data-id="${x.id}"><div><strong>${esc(x.destination)}</strong><p>${esc(x.number)} · ${esc(x.employee_name||'')} · ${esc(x.date_from)} → ${esc(x.date_to)}</p></div><span class="status-pill">${esc(statusLabel(x.status))}</span></article>`).join('') : '<article class="card empty-card"><div><strong>Brak pozycji</strong><p>Nie ma delegacji w tym widoku.</p></div></article>';
    list.querySelectorAll('[data-id]').forEach(el=>el.addEventListener('click',()=>openDetail(Number(el.dataset.id))));
  }

  async function load(mode='all') {
    show('list');
    list.innerHTML='<article class="card">Ładowanie…</article>';
    try { const data=await api('./api/delegations/list.php'); items=data.items||[]; renderRows(mode); }
    catch(e){ list.innerHTML=`<article class="card"><strong>Błąd</strong><p>${esc(e.message)}</p></article>`; }
  }

  function actionButton(label, action, cls='primary') {
    return `<button class="${cls}" data-action="${action}">${esc(label)}</button>`;
  }

  function openDetail(id) {
    const x=items.find(i=>Number(i.id)===Number(id)); if(!x)return;
    show('none'); detail.classList.remove('hidden');
    let actions='';
    const isOwner=true; // visibility is already enforced server-side; server rechecks every transition.
    if (x.status==='pending' && (roles.has('manager')||roles.has('super_admin'))) actions += actionButton('✓ Zatwierdź','approve')+actionButton('✕ Odrzuć','reject','danger');
    if (x.status==='approved' && isOwner) actions += actionButton('▶ Rozpocznij','start');
    if (x.status==='started' && isOwner) actions += actionButton('■ Zakończ','finish');
    if (x.status==='finished' && (roles.has('accounting')||roles.has('super_admin'))) actions += actionButton('Zaksięguj','accounting_book')+actionButton('Oznacz wypłatę','accounting_pay');
    detail.innerHTML=`<article class="card"><button id="backBtn" class="back">←</button><h2>${esc(x.destination)}</h2><p class="muted">${esc(x.number)}</p><div class="detail"><div><span>Pracownik</span><strong>${esc(x.employee_name)}</strong></div><div><span>Przełożony</span><strong>${esc(x.manager_name||'brak')}</strong></div><div><span>Status</span><strong>${esc(statusLabel(x.status))}</strong></div><div><span>Termin</span><strong>${esc(x.date_from)} → ${esc(x.date_to)}</strong></div><div><span>Cel</span><strong>${esc(x.purpose)}</strong></div><div><span>Księgowość</span><strong>${esc(x.accounting_status)}</strong></div></div><div class="mobile-actions">${actions}</div></article>`;
    document.getElementById('backBtn').onclick=()=>load();
    detail.querySelectorAll('[data-action]').forEach(btn=>btn.onclick=()=>transition(id,btn.dataset.action));
  }

  async function geo() {
    if (!navigator.geolocation) return {};
    try { const pos=await new Promise((ok,err)=>navigator.geolocation.getCurrentPosition(ok,err,{enableHighAccuracy:true,timeout:8000,maximumAge:0})); return {lat:pos.coords.latitude,lng:pos.coords.longitude}; }
    catch { return {}; }
  }

  async function transition(id, action) {
    const payload={id,action};
    if (action==='reject') payload.note=prompt('Powód odrzucenia:','')||'';
    if (action==='start'||action==='finish') {
      const odo=prompt(action==='start'?'Stan licznika na początku:':'Stan licznika na końcu:','');
      if (odo===null)return; payload.odometer_km=Number(odo); Object.assign(payload,await geo());
    }
    try { await api('./api/delegations/transition.php',{method:'POST',body:JSON.stringify(payload)}); await load(); }
    catch(e){ alert(e.message); }
  }

  document.getElementById('tabList').onclick=()=>load();
  document.getElementById('tabNew').onclick=()=>{show('new');document.getElementById('tabNew').classList.add('active')};
  document.getElementById('tabApprovals')?.addEventListener('click',()=>load('approvals'));
  document.getElementById('tabAccounting')?.addEventListener('click',()=>load('accounting'));
  form.addEventListener('submit',async e=>{e.preventDefault();const f=new FormData(form);const payload=Object.fromEntries(f.entries());try{await api('./api/delegations/create.php',{method:'POST',body:JSON.stringify(payload)});form.reset();await load()}catch(err){alert(err.message)}});
  load();
})();
