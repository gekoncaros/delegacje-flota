(() => {
  const form = document.getElementById('inviteAdminForm');
  const errorBox = document.getElementById('inviteAdminError');
  const resultBox = document.getElementById('inviteResult');
  const inviteUrl = document.getElementById('inviteUrl');
  const copyButton = document.getElementById('copyInviteButton');
  const usersList = document.getElementById('usersList');
  const refreshButton = document.getElementById('refreshUsers');
  let csrfToken = '';

  const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[char]));

  async function loadSession() {
    const response = await fetch('../api/auth/me.php', { credentials: 'same-origin' });
    const payload = await response.json();
    if (!response.ok || !payload.ok) throw new Error(payload.error || 'Brak aktywnej sesji.');
    csrfToken = payload.csrfToken || '';
  }

  async function loadUsers() {
    usersList.innerHTML = '<article class="card empty-card"><div>Ładowanie…</div></article>';
    try {
      const response = await fetch('../api/admin/users.php', { credentials: 'same-origin' });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Nie udało się pobrać użytkowników.');
      if (!payload.users.length) {
        usersList.innerHTML = '<article class="card empty-card"><div><strong>Brak użytkowników</strong></div></article>';
        return;
      }
      usersList.innerHTML = payload.users.map(user => `
        <article class="card list-card">
          <div>
            <strong>${esc(user.displayName)}</strong>
            <p>${esc(user.email)}</p>
            <p>${esc((user.roles || []).join(', ') || 'bez roli')} · ${user.active ? 'aktywne' : 'wyłączone'}</p>
          </div>
          <span class="status-pill">#${user.id}</span>
        </article>
      `).join('');
    } catch (error) {
      usersList.innerHTML = `<div class="alert error">${esc(error.message || 'Błąd')}</div>`;
    }
  }

  form?.addEventListener('submit', async event => {
    event.preventDefault();
    errorBox.hidden = true;
    resultBox.hidden = true;
    const data = new FormData(form);

    try {
      if (!csrfToken) await loadSession();
      const response = await fetch('../api/admin/invite-user.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({
          displayName: String(data.get('displayName') || ''),
          email: String(data.get('email') || ''),
          roleCode: String(data.get('roleCode') || 'employee'),
          ttlHours: Number(data.get('ttlHours') || 48)
        })
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.error || 'Nie udało się utworzyć zaproszenia.');
      inviteUrl.value = payload.inviteUrl;
      resultBox.hidden = false;
      form.reset();
    } catch (error) {
      errorBox.textContent = error.message || 'Nie udało się utworzyć zaproszenia.';
      errorBox.hidden = false;
    }
  });

  copyButton?.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(inviteUrl.value);
      copyButton.textContent = 'Skopiowano';
      setTimeout(() => { copyButton.textContent = 'Kopiuj link'; }, 1200);
    } catch {
      inviteUrl.select();
      document.execCommand('copy');
    }
  });

  refreshButton?.addEventListener('click', loadUsers);

  (async () => {
    try {
      await loadSession();
      await loadUsers();
    } catch (error) {
      errorBox.textContent = error.message || 'Brak dostępu.';
      errorBox.hidden = false;
    }
  })();
})();
