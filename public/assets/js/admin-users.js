(() => {
  const form = document.getElementById('inviteAdminForm');
  const errorBox = document.getElementById('inviteAdminError');
  const resultBox = document.getElementById('inviteResult');
  const inviteUrl = document.getElementById('inviteUrl');
  const copyButton = document.getElementById('copyInviteButton');
  const usersList = document.getElementById('usersList');
  const refreshButton = document.getElementById('refreshUsers');
  const auditList = document.getElementById('auditList');
  const refreshAuditButton = document.getElementById('refreshAudit');
  const roleLabels = { employee:'Pracownik', manager:'Przełożony', accounting:'Księgowość', fleet_admin:'Administrator floty', super_admin:'Super Admin' };
  let csrfToken = '';
  let currentUserId = 0;
  let users = [];

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'
  }[char]));

  async function request(url, options = {}) {
    const response = await fetch(url, { credentials:'same-origin', ...options });
    const payload = await response.json().catch(() => ({ ok:false, error:'Nieprawidłowa odpowiedź serwera.' }));
    if (!response.ok || payload.ok === false) throw new Error(payload.error || 'Operacja nie powiodła się.');
    return payload;
  }

  async function loadSession() {
    const payload = await request('../api/auth/me.php');
    csrfToken = payload.csrfToken || '';
    currentUserId = Number(payload.user?.id || 0);
  }

  function managerOptions(user) {
    const eligible = users.filter(candidate => candidate.active && candidate.id !== user.id && (candidate.roles.includes('manager') || candidate.roles.includes('super_admin')));
    return `<option value="">Brak przełożonego</option>${eligible.map(candidate => `<option value="${candidate.id}" ${candidate.id === user.managerUserId ? 'selected' : ''}>${esc(candidate.displayName)}</option>`).join('')}`;
  }

  function roleOptions(user) {
    return Object.entries(roleLabels).map(([code, label]) => `<label class="admin-role"><input type="checkbox" name="roles" value="${code}" ${user.roles.includes(code) ? 'checked' : ''} ${user.id === currentUserId && code === 'super_admin' ? 'disabled' : ''}><span>${esc(label)}</span></label>`).join('');
  }

  function renderUsers() {
    if (!users.length) {
      usersList.innerHTML = '<article class="card empty-card"><div><strong>Brak użytkowników</strong></div></article>';
      return;
    }
    usersList.innerHTML = users.map(user => `
      <article class="card admin-user-card">
        <div class="admin-user-summary">
          <div><strong>${esc(user.displayName)}</strong><p>${esc(user.email)}</p><p>${esc(user.roles.map(role => roleLabels[role] || role).join(', ') || 'bez roli')} · ${user.active ? 'aktywne' : 'wyłączone'}${user.managerName ? ` · przełożony: ${esc(user.managerName)}` : ''}</p></div>
          <div class="admin-user-tools"><span class="status-pill">#${user.id}</span><button class="mini-action" type="button" data-edit="${user.id}">Edytuj</button></div>
        </div>
        <form class="admin-user-editor hidden" data-user-form="${user.id}">
          <label>Imię i nazwisko<input name="displayName" maxlength="190" value="${esc(user.displayName)}" required></label>
          <label>Przełożony<select name="managerUserId">${managerOptions(user)}</select></label>
          <fieldset><legend>Role</legend><div class="admin-roles">${roleOptions(user)}</div></fieldset>
          <label class="check-row"><input type="checkbox" name="active" value="1" ${user.active ? 'checked' : ''} ${user.id === currentUserId ? 'disabled' : ''}><span>Konto aktywne</span></label>
          ${user.id === currentUserId ? '<p class="hint">Dla bezpieczeństwa nie możesz wyłączyć własnego konta ani odebrać sobie roli Super Admina.</p>' : ''}
          <div class="admin-editor-actions"><button class="primary-btn dark-btn" type="submit">Zapisz</button><button class="mini-action" type="button" data-cancel-edit="${user.id}">Anuluj</button></div>
        </form>
      </article>
    `).join('');
    usersList.querySelectorAll('[data-edit]').forEach(button => button.addEventListener('click', () => toggleEditor(Number(button.dataset.edit), true)));
    usersList.querySelectorAll('[data-cancel-edit]').forEach(button => button.addEventListener('click', () => toggleEditor(Number(button.dataset.cancelEdit), false)));
    usersList.querySelectorAll('[data-user-form]').forEach(editor => editor.addEventListener('submit', saveUser));
  }

  function toggleEditor(id, visible) {
    usersList.querySelector(`[data-user-form="${id}"]`)?.classList.toggle('hidden', !visible);
  }

  async function loadUsers() {
    usersList.innerHTML = '<article class="card empty-card"><div>Ładowanie…</div></article>';
    try {
      const payload = await request('../api/admin/users.php');
      users = payload.users || [];
      renderUsers();
    } catch (error) {
      usersList.innerHTML = `<div class="alert error">${esc(error.message || 'Błąd')}</div>`;
    }
  }

  function auditCard(title, detail, createdAt, success = true) {
    return `<article class="card list-card"><div><strong>${esc(title)}</strong><p>${esc(detail)}</p><p>${esc(createdAt)}</p></div><span class="status-pill">${success ? 'OK' : 'BŁĄD'}</span></article>`;
  }

  async function loadAudit() {
    if (!auditList) return;
    auditList.innerHTML = '<article class="card empty-card"><div>Ładowanie…</div></article>';
    try {
      const payload = await request('../api/admin/audit.php?limit=25');
      const activity = payload.audit?.activity || [];
      const logins = payload.audit?.logins || [];
      const entries = [
        ...activity.map(item => ({
          sort:item.createdAt,
          html:auditCard(item.action, `${item.actorName || item.actorEmail || 'Nieznany użytkownik'}${item.objectType ? ` · ${item.objectType}${item.objectId ? ` #${item.objectId}` : ''}` : ''}${item.ipAddress ? ` · ${item.ipAddress}` : ''}`, item.createdAt)
        })),
        ...logins.map(item => ({
          sort:item.createdAt,
          html:auditCard(item.success ? 'auth.login.success' : 'auth.login.failure', `${item.actorName || item.email || 'Nieznany użytkownik'}${item.ipAddress ? ` · ${item.ipAddress}` : ''}`, item.createdAt, item.success)
        }))
      ].sort((a, b) => String(b.sort).localeCompare(String(a.sort))).slice(0, 25);
      auditList.innerHTML = entries.length ? entries.map(item => item.html).join('') : '<article class="card empty-card"><div><strong>Brak zdarzeń</strong></div></article>';
    } catch (error) {
      auditList.innerHTML = `<div class="alert error">${esc(error.message || 'Nie udało się pobrać dziennika.')}</div>`;
    }
  }

  async function saveUser(event) {
    event.preventDefault();
    const editor = event.currentTarget;
    const id = Number(editor.dataset.userForm);
    const current = users.find(user => user.id === id);
    const data = new FormData(editor);
    const roles = data.getAll('roles').map(String);
    if (id === currentUserId && !roles.includes('super_admin')) roles.push('super_admin');
    const submit = editor.querySelector('[type="submit"]');
    submit.disabled = true;
    try {
      await request('../api/admin/update-user.php', {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-Token':csrfToken },
        body:JSON.stringify({ id, displayName:String(data.get('displayName') || ''), managerUserId:data.get('managerUserId') || null, roles, active:id === currentUserId ? Boolean(current?.active) : data.has('active') })
      });
      errorBox.hidden = true;
      await loadUsers();
    } catch (error) {
      errorBox.textContent = error.message || 'Nie udało się zapisać użytkownika.';
      errorBox.hidden = false;
    } finally {
      submit.disabled = false;
    }
  }

  form?.addEventListener('submit', async event => {
    event.preventDefault(); errorBox.hidden = true; resultBox.hidden = true;
    const data = new FormData(form);
    try {
      const payload = await request('../api/admin/invite-user.php', { method:'POST', headers:{ 'Content-Type':'application/json', 'X-CSRF-Token':csrfToken }, body:JSON.stringify({ displayName:String(data.get('displayName') || ''), email:String(data.get('email') || ''), roleCode:String(data.get('roleCode') || 'employee'), ttlHours:Number(data.get('ttlHours') || 48) }) });
      inviteUrl.value = payload.inviteUrl; resultBox.hidden = false; form.reset();
    } catch (error) { errorBox.textContent = error.message || 'Nie udało się utworzyć zaproszenia.'; errorBox.hidden = false; }
  });

  copyButton?.addEventListener('click', async () => {
    try { await navigator.clipboard.writeText(inviteUrl.value); copyButton.textContent = 'Skopiowano'; setTimeout(() => { copyButton.textContent = 'Kopiuj link'; }, 1200); }
    catch { inviteUrl.select(); document.execCommand('copy'); }
  });
  refreshButton?.addEventListener('click', loadUsers);
  refreshAuditButton?.addEventListener('click', loadAudit);
  (async () => { try { await loadSession(); await Promise.all([loadUsers(), loadAudit()]); } catch (error) { errorBox.textContent = error.message || 'Brak dostępu.'; errorBox.hidden = false; } })();
})();
