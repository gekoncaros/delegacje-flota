(() => {
  const form = document.getElementById('inviteForm');
  const errorBox = document.getElementById('inviteError');
  const successBox = document.getElementById('inviteSuccess');
  const button = document.getElementById('inviteButton');

  if (!form) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();
    errorBox.hidden = true;
    successBox.hidden = true;

    const data = new FormData(form);
    const password = String(data.get('password') || '');
    const passwordConfirm = String(data.get('passwordConfirm') || '');
    const token = String(data.get('token') || '');

    if (!token) {
      errorBox.textContent = 'Brak tokenu zaproszenia.';
      errorBox.hidden = false;
      return;
    }

    if (password !== passwordConfirm) {
      errorBox.textContent = 'Hasła nie są identyczne.';
      errorBox.hidden = false;
      return;
    }

    button.disabled = true;
    button.textContent = 'Aktywacja…';

    try {
      const response = await fetch('./api/auth/accept-invite.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token, password })
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Nie udało się aktywować konta.');
      }

      successBox.hidden = false;
      form.hidden = true;
      setTimeout(() => window.location.replace('./'), 700);
    } catch (error) {
      errorBox.textContent = error instanceof Error ? error.message : 'Nie udało się aktywować konta.';
      errorBox.hidden = false;
    } finally {
      button.disabled = false;
      button.textContent = 'Aktywuj konto';
    }
  });
})();
