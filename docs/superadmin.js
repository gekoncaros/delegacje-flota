(() => {
  if (typeof state === 'undefined' || typeof page === 'undefined' || typeof profile === 'undefined') return;

  const KEY = 'delegacjeSuperAdminConfigV1';
  const defaults = {
    company: {
      appName: 'Delegacje + Flota',
      companyName: 'Firma demonstracyjna',
      nip: '',
      address: '',
      postalCode: '',
      city: '',
      country: 'Polska',
      supportEmail: '',
      primaryColor: '#111827',
      logoDataUrl: ''
    },
    mail: {
      senderName: 'Delegacje + Flota',
      senderEmail: '',
      accountingEmail: '',
      supervisorEmail: '',
      hrEmail: '',
      fleetEmail: '',
      smtpHost: '',
      smtpPort: 587,
      encryption: 'STARTTLS'
    },
    workflow: {
      approvalRequired: true,
      gpsStartEnd: true,
      maxUploadMb: 10,
      notifyAccounting: true,
      notifySupervisor: true,
      notifyFleet: true
    },
    legal: {
      rodo: 'Administratorem danych jest firma wdrażająca system. Dane są przetwarzane wyłącznie w zakresie niezbędnym do obsługi delegacji, rozliczeń i floty.',
      regulations: 'Korzystanie z systemu wymaga posiadania indywidualnego konta. Login i sesja użytkownika stanowią poświadczenie osoby wykonującej operacje w systemie. Zabronione jest udostępnianie danych logowania innym osobom.',
      privacyContact: ''
    },
    users: [
      {id: 1, name: 'Jan Kowalski', email: '', role: 'employee', active: true},
      {id: 2, name: 'Anna Nowak', email: '', role: 'manager', active: true},
      {id: 3, name: 'Piotr Wiśniewski', email: '', role: 'fleet_admin', active: true}
    ],
    audit: []
  };

  let cfg = JSON.parse(localStorage.getItem(KEY) || 'null') || structuredClone(defaults);
  for (const [k,v] of Object.entries(defaults)) if (cfg[k] === undefined) cfg[k] = structuredClone(v);

  function persist(action, meta = '') {
    cfg.audit.unshift({ id: Date.now() + Math.random(), action, meta, at: Date.now() });
    cfg.audit = cfg.audit.slice(0, 100);
    localStorage.setItem(KEY, JSON.stringify(cfg));
    applyBranding();
  }

  function roleLabel(role) {
    return ({employee:'Pracownik', manager:'Przełożony', accounting:'Księgowość', fleet_admin:'Admin floty', super_admin:'Super Admin'})[role] || role;
  }

  function applyBranding() {
    document.documentElement.style.setProperty('--brand-color', cfg.company.primaryColor || '#111827');
    document.title = cfg.company.appName || 'Delegacje + Flota';
    document.querySelectorAll('.top .eyebrow,.page .eyebrow').forEach(el => {
      if (el.textContent.trim() === 'Delegacje + Flota') el.textContent = cfg.company.appName;
    });
    const top = document.querySelector('.top');
    if (top && cfg.company.logoDataUrl && !top.querySelector('.custom-brand-logo')) {
      top.insertAdjacentHTML('afterbegin', `<img class="custom-brand-logo" src="${cfg.company.logoDataUrl}" alt="Logo">`);
    }
    const main = document.querySelector('main');
    if (main && !main.querySelector('#legalFooter')) {
      main.insertAdjacentHTML('beforeend', `<div id="legalFooter" class="legal-footer"><button onclick="openLegalInfo('rodo')">RODO</button><button onclick="openLegalInfo('regulations')">Regulamin</button></div>`);
    }
  }

  const baseShell = shell;
  shell = function(...args) { baseShell(...args); applyBranding(); };
  const basePage = page;
  page = function(...args) { basePage(...args); applyBranding(); };

  function dashboard() {
    const active = cfg.users.filter(u => u.active).length;
    const admins = cfg.users.filter(u => ['super_admin','fleet_admin','accounting'].includes(u.role) && u.active).length;
    const mailRoutes = ['accountingEmail','supervisorEmail','hrEmail','fleetEmail'].filter(k => cfg.mail[k]).length;
    page('Super Admin', `
      <div class="admin-kpis">
        <div class="stat"><strong>${active}</strong><small>aktywni użytkownicy</small></div>
        <div class="stat"><strong>${admins}</strong><small>role administracyjne</small></div>
        <div class="stat"><strong>${mailRoutes}/4</strong><small>routing e-mail</small></div>
        <div class="stat"><strong>${cfg.audit.length}</strong><small>zdarzenia audytu</small></div>
      </div>
      <div class="superadmin-grid">
        <button class="action" onclick="superAdminCompany()"><span class="emoji">🏢</span><strong>Firma i branding</strong><small>Dane, logo, kolor</small></button>
        <button class="action" onclick="superAdminUsers()"><span class="emoji">👥</span><strong>Użytkownicy i role</strong><small>Uprawnienia i aktywność</small></button>
        <button class="action" onclick="superAdminMail()"><span class="emoji">✉️</span><strong>E-mail i routing</strong><small>Księgowość, HR, przełożeni</small></button>
        <button class="action" onclick="superAdminWorkflow()"><span class="emoji">⚙️</span><strong>Workflow</strong><small>GPS, akceptacje, upload</small></button>
        <button class="action" onclick="superAdminLegal()"><span class="emoji">⚖️</span><strong>RODO i regulamin</strong><small>Treści publiczne</small></button>
        <button class="action" onclick="superAdminAudit()"><span class="emoji">🧾</span><strong>Audit log</strong><small>Zmiany administracyjne</small></button>
      </div>
      <div class="card superadmin-card">
        <h2>Przenoszenie konfiguracji</h2>
        <p class="muted">Eksport obejmuje konfigurację systemu, branding i role. Nie eksportuje delegacji, kosztów, haseł ani dokumentów użytkowników.</p>
        <div class="config-actions"><button class="btn dark" onclick="exportSystemConfig()">Eksport JSON</button><button class="btn light" onclick="importSystemConfig()">Import JSON</button></div>
      </div>
    `);
  }

  function companyPage() {
    const c = cfg.company;
    page('Firma i branding', `
      <form class="card form" onsubmit="saveCompanySettings(event)">
        <label>Nazwa aplikacji<input name="appName" value="${esc(c.appName)}" required></label>
        <label>Nazwa firmy<input name="companyName" value="${esc(c.companyName)}" required></label>
        <div class="row2"><label>NIP<input name="nip" value="${esc(c.nip)}"></label><label>Kraj<input name="country" value="${esc(c.country)}"></label></div>
        <label>Adres<input name="address" value="${esc(c.address)}"></label>
        <div class="row2"><label>Kod pocztowy<input name="postalCode" value="${esc(c.postalCode)}"></label><label>Miasto<input name="city" value="${esc(c.city)}"></label></div>
        <label>E-mail wsparcia<input type="email" name="supportEmail" value="${esc(c.supportEmail)}"></label>
        <label>Kolor przewodni<input type="color" name="primaryColor" value="${esc(c.primaryColor || '#111827')}"></label>
        <button type="button" class="btn light" onclick="chooseCompanyLogo()">Wybierz logo</button>
        <div class="brand-preview">${c.logoDataUrl?`<img src="${c.logoDataUrl}" alt="Logo">`:'<div class="brand-dot"></div>'}<div><strong>${esc(c.companyName)}</strong><small class="muted">${esc(c.appName)}</small></div></div>
        <button class="btn dark full">Zapisz branding</button>
      </form>
    `);
  }

  function usersPage() {
    page('Użytkownicy i role', `
      <div class="card superadmin-card">
        <h2>Dodaj użytkownika</h2>
        <form class="form" onsubmit="addSystemUser(event)">
          <label>Imię i nazwisko<input name="name" required></label>
          <label>E-mail<input name="email" type="email" required></label>
          <label>Rola<select name="role"><option value="employee">Pracownik</option><option value="manager">Przełożony</option><option value="accounting">Księgowość</option><option value="fleet_admin">Admin floty</option><option value="super_admin">Super Admin</option></select></label>
          <button class="btn dark full">Dodaj</button>
        </form>
      </div>
      <div class="stack">${cfg.users.map(u => `<div class="card admin-user"><div><strong>${esc(u.name)}</strong><small>${esc(u.email || 'brak e-mail')} · <span class="role-chip">${roleLabel(u.role)}</span> · ${u.active?'aktywny':'wyłączony'}</small></div><button class="btn light" onclick="editSystemUser(${u.id})">Edytuj</button></div>`).join('')}</div>
    `);
  }

  function mailPage() {
    const m = cfg.mail;
    page('E-mail i routing', `
      <form class="card form" onsubmit="saveMailSettings(event)">
        <div class="row2"><label>Nazwa nadawcy<input name="senderName" value="${esc(m.senderName)}"></label><label>Adres nadawcy<input type="email" name="senderEmail" value="${esc(m.senderEmail)}"></label></div>
        <label>Księgowość<input type="email" name="accountingEmail" value="${esc(m.accountingEmail)}" placeholder="ksiegowosc@firma.pl"></label>
        <label>Domyślny przełożony<input type="email" name="supervisorEmail" value="${esc(m.supervisorEmail)}"></label>
        <label>HR<input type="email" name="hrEmail" value="${esc(m.hrEmail)}"></label>
        <label>Flota / administracja<input type="email" name="fleetEmail" value="${esc(m.fleetEmail)}"></label>
        <div class="row2"><label>SMTP host<input name="smtpHost" value="${esc(m.smtpHost)}"></label><label>Port<input type="number" name="smtpPort" value="${Number(m.smtpPort||587)}"></label></div>
        <label>Szyfrowanie<select name="encryption"><option ${m.encryption==='STARTTLS'?'selected':''}>STARTTLS</option><option ${m.encryption==='TLS'?'selected':''}>TLS</option><option ${m.encryption==='SSL'?'selected':''}>SSL</option></select></label>
        <div class="secret-warning">Hasło SMTP / klucz API nie jest przechowywany w PWA ani w GitHub. W produkcji sekret pozostaje wyłącznie po stronie serwera.</div>
        <button class="btn dark full">Zapisz routing</button>
      </form>
    `);
  }

  function workflowPage() {
    const w = cfg.workflow;
    page('Workflow i bezpieczeństwo', `
      <form class="card form" onsubmit="saveWorkflowSettings(event)">
        <label class="checkline"><input type="checkbox" name="approvalRequired" ${w.approvalRequired?'checked':''}> Delegacja wymaga akceptacji przełożonego</label>
        <label class="checkline"><input type="checkbox" name="gpsStartEnd" ${w.gpsStartEnd?'checked':''}> Proponuj GPS przy starcie/końcu delegacji</label>
        <label>Maksymalny rozmiar dokumentu (MB)<input type="number" min="1" max="50" name="maxUploadMb" value="${Number(w.maxUploadMb||10)}"></label>
        <label class="checkline"><input type="checkbox" name="notifyAccounting" ${w.notifyAccounting?'checked':''}> Powiadamiaj księgowość</label>
        <label class="checkline"><input type="checkbox" name="notifySupervisor" ${w.notifySupervisor?'checked':''}> Powiadamiaj przełożonego</label>
        <label class="checkline"><input type="checkbox" name="notifyFleet" ${w.notifyFleet?'checked':''}> Powiadamiaj administratora floty</label>
        <button class="btn dark full">Zapisz workflow</button>
      </form>
    `);
  }

  function legalPage() {
    const l = cfg.legal;
    page('RODO i regulamin', `
      <form class="card form" onsubmit="saveLegalSettings(event)">
        <label>Publiczna informacja RODO<textarea name="rodo" rows="7">${esc(l.rodo)}</textarea></label>
        <label>Regulamin / poświadczenie loginu<textarea name="regulations" rows="9">${esc(l.regulations)}</textarea></label>
        <label>Kontakt ds. prywatności<input type="email" name="privacyContact" value="${esc(l.privacyContact)}"></label>
        <button class="btn dark full">Zapisz treści</button>
      </form>
    `);
  }

  function auditPage() {
    page('Audit log konfiguracji', cfg.audit.length ? `<div class="stack">${cfg.audit.map(a => `<div class="card admin-audit"><strong>${esc(a.action)}</strong><span>${new Date(a.at).toLocaleString('pl-PL')}</span>${a.meta?`<p class="muted">${esc(a.meta)}</p>`:''}</div>`).join('')}</div>` : '<div class="card empty"><div class="ico">🧾</div><div><strong>Brak zmian</strong><p>Zmiany Super Admina pojawią się tutaj.</p></div></div>');
  }

  window.saveCompanySettings = e => {
    e.preventDefault(); const f = new FormData(e.target);
    Object.assign(cfg.company, Object.fromEntries(['appName','companyName','nip','country','address','postalCode','city','supportEmail','primaryColor'].map(k => [k, f.get(k) || ''])));
    persist('company.settings.update', cfg.company.companyName); companyPage();
  };

  window.chooseCompanyLogo = () => {
    const input = document.createElement('input'); input.type='file'; input.accept='image/png,image/jpeg,image/webp,image/svg+xml';
    input.onchange = () => { const file=input.files?.[0]; if(!file) return; if(file.size>700*1024) return alert('Logo jest za duże. Maksymalnie 700 KB w wersji testowej.'); const reader=new FileReader(); reader.onload=()=>{cfg.company.logoDataUrl=String(reader.result||''); persist('company.logo.update', file.name); companyPage();}; reader.readAsDataURL(file); };
    input.click();
  };

  window.addSystemUser = e => {
    e.preventDefault(); const f=new FormData(e.target); cfg.users.push({id:Date.now(),name:String(f.get('name')),email:String(f.get('email')),role:String(f.get('role')),active:true}); persist('user.create', String(f.get('email'))); usersPage();
  };

  window.editSystemUser = id => {
    const u=cfg.users.find(x=>Number(x.id)===Number(id)); if(!u) return;
    page(`Użytkownik: ${u.name}`, `<form class="card form" onsubmit="saveSystemUser(event,${u.id})"><label>Imię i nazwisko<input name="name" value="${esc(u.name)}" required></label><label>E-mail<input type="email" name="email" value="${esc(u.email)}"></label><label>Rola<select name="role">${['employee','manager','accounting','fleet_admin','super_admin'].map(r=>`<option value="${r}" ${u.role===r?'selected':''}>${roleLabel(r)}</option>`).join('')}</select></label><label class="checkline"><input type="checkbox" name="active" ${u.active?'checked':''}> Konto aktywne</label><button class="btn dark full">Zapisz użytkownika</button></form>`);
  };

  window.saveSystemUser = (e,id) => {
    e.preventDefault(); const f=new FormData(e.target),u=cfg.users.find(x=>Number(x.id)===Number(id)); if(!u) return; Object.assign(u,{name:String(f.get('name')),email:String(f.get('email')||''),role:String(f.get('role')),active:f.get('active')==='on'}); persist('user.update', u.email||u.name); usersPage();
  };

  window.saveMailSettings = e => {
    e.preventDefault(); const f=new FormData(e.target); ['senderName','senderEmail','accountingEmail','supervisorEmail','hrEmail','fleetEmail','smtpHost','encryption'].forEach(k=>cfg.mail[k]=String(f.get(k)||'')); cfg.mail.smtpPort=Number(f.get('smtpPort')||587); persist('mail.routing.update'); mailPage();
  };

  window.saveWorkflowSettings = e => {
    e.preventDefault(); const f=new FormData(e.target); cfg.workflow={approvalRequired:f.get('approvalRequired')==='on',gpsStartEnd:f.get('gpsStartEnd')==='on',maxUploadMb:Number(f.get('maxUploadMb')||10),notifyAccounting:f.get('notifyAccounting')==='on',notifySupervisor:f.get('notifySupervisor')==='on',notifyFleet:f.get('notifyFleet')==='on'}; persist('workflow.update'); workflowPage();
  };

  window.saveLegalSettings = e => {
    e.preventDefault(); const f=new FormData(e.target); cfg.legal={rodo:String(f.get('rodo')||''),regulations:String(f.get('regulations')||''),privacyContact:String(f.get('privacyContact')||'')}; persist('legal.update'); legalPage();
  };

  window.openLegalInfo = kind => {
    const title=kind==='rodo'?'RODO / prywatność':'Regulamin'; const text=kind==='rodo'?cfg.legal.rodo:cfg.legal.regulations; page(title,`<div class="card"><p style="white-space:pre-wrap;line-height:1.6">${esc(text)}</p>${cfg.legal.privacyContact?`<p class="muted">Kontakt: ${esc(cfg.legal.privacyContact)}</p>`:''}</div>`);
  };

  window.exportSystemConfig = () => {
    const exportData={version:1,exportedAt:new Date().toISOString(),company:cfg.company,mail:cfg.mail,workflow:cfg.workflow,legal:cfg.legal,users:cfg.users};
    const blob=new Blob([JSON.stringify(exportData,null,2)],{type:'application/json'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=`delegacje-config-${new Date().toISOString().slice(0,10)}.json`; a.click(); setTimeout(()=>URL.revokeObjectURL(a.href),1000); persist('config.export');
  };

  window.importSystemConfig = () => {
    const input=document.createElement('input'); input.type='file'; input.accept='application/json,.json'; input.onchange=()=>{const file=input.files?.[0];if(!file)return;const reader=new FileReader();reader.onload=()=>{try{const data=JSON.parse(String(reader.result||'{}'));['company','mail','workflow','legal','users'].forEach(k=>{if(data[k]!==undefined)cfg[k]=data[k]});persist('config.import',file.name);dashboard()}catch{alert('Nieprawidłowy plik konfiguracji.')}};reader.readAsText(file)};input.click();
  };

  window.superAdminDashboard = dashboard;
  window.superAdminCompany = companyPage;
  window.superAdminUsers = usersPage;
  window.superAdminMail = mailPage;
  window.superAdminWorkflow = workflowPage;
  window.superAdminLegal = legalPage;
  window.superAdminAudit = auditPage;

  const previousProfile = profile;
  profile = function() {
    previousProfile();
    const main=document.querySelector('main'); if(!main) return;
    main.insertAdjacentHTML('beforeend', `<div class="card superadmin-entry"><strong>🛡️ Super Admin</strong><p class="muted">Użytkownicy, role, firma, branding, routing e-mail, RODO i konfiguracja całego systemu.</p><button class="btn dark full" onclick="superAdminDashboard()">Otwórz Super Admin</button></div>`);
    applyBranding();
  };

  applyBranding();
})();