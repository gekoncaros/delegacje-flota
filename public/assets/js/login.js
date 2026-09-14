(() => {
  const form = document.getElementById('loginForm');
  const errorBox = document.getElementById('loginError');
  const button = document.getElementById('loginButton');

  if (!form) return;

  form.addEventListener('submit', async event => {
    event.preventDefault();
    errorBox.hidden = true;
    button.disabled = true;
    button.textContent = 'Logowanie…';

    const data = new FormData(form);

    try {
      const response = await fetch('./api/auth/login.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: String(data.get('email') || ''),
          password: String(data.get('password') || '')
        })
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        throw new Error(payload.error || 'Nie udało się zalogować.');
      }

      window.location.replace('./');
    } catch (error) {
      errorBox.textContent = error instanceof Error ? error.message : 'Nie udało się zalogować.';
      errorBox.hidden = false;
    } finally {
      button.disabled = false;
      button.textContent = 'Zaloguj';
    }
  });
})();
