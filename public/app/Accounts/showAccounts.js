/**
 * Accounts screen owner: forms and transient UI, never identity or audit authority.
 * Each route mount has a cancellable lifetime supplied by the navigator.
 */
export function renderAccounts(screen, { page, outlet, signal, onDispose }, { client, navigate }) {
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = screen.path === '/app/login' ? 'Sign in'
    : screen.path === '/app/register' ? 'Create account' : 'Home';
  heading.tabIndex = -1;
  section.append(heading);
  outlet.replaceChildren(section);
  heading.focus?.();

  function status() {
    const message = page.createElement('p');
    message.setAttribute('role', 'status');
    message.setAttribute('aria-live', 'polite');
    section.append(message);
    return message;
  }

  if (screen.path === '/app/login' || screen.path === '/app/register') {
    const isLogin = screen.path === '/app/login';
    const form = page.createElement('form');
    const usernameLabel = page.createElement('label');
    usernameLabel.textContent = 'Username';
    usernameLabel.htmlFor = 'account-username';
    const username = page.createElement('input');
    username.id = 'account-username';
    username.name = 'username';
    username.autocomplete = 'username';
    username.required = true;
    username.maxLength = isLogin ? 4096 : 64;

    const passwordLabel = page.createElement('label');
    passwordLabel.textContent = 'Password';
    passwordLabel.htmlFor = 'account-password';
    const password = page.createElement('input');
    password.id = 'account-password';
    password.name = 'password';
    password.type = 'password';
    password.autocomplete = isLogin ? 'current-password' : 'new-password';
    password.required = true;

    const submitButton = page.createElement('button');
    submitButton.type = 'submit';
    submitButton.textContent = isLogin ? 'Sign in' : 'Register';
    const feedback = page.createElement('p');
    feedback.setAttribute('role', 'alert');
    feedback.setAttribute('aria-live', 'assertive');
    feedback.tabIndex = -1;
    form.append(usernameLabel, username, passwordLabel, password, submitButton, feedback);
    section.append(form);
    username.focus?.();

    const alternate = page.createElement('a');
    alternate.href = isLogin ? '/app/register' : '/app/login';
    alternate.textContent = isLogin ? 'Create an account' : 'Already registered? Sign in';
    section.append(alternate);

    let pending = false;
    async function submit(event) {
      event.preventDefault();
      if (pending || signal.aborted) return;
      pending = true;
      submitButton.disabled = true;
      feedback.textContent = '';
      feedback.setAttribute('role', 'status');
      try {
        const result = await client.request(isLogin ? '/api/accounts/login' : '/api/accounts/register', {
          method: 'POST', body: { username: username.value, password: password.value }, signal,
        });
        if (signal.aborted) return;
        if (isLogin) {
          client.setCsrfToken(result.csrfToken);
          navigate('/app/home');
        } else {
          feedback.textContent = 'Registration complete. Sign in with your account.';
        }
      } catch (error) {
        if (!signal.aborted && error?.name !== 'AbortError') {
          feedback.setAttribute('role', 'alert');
          feedback.textContent = error?.message || 'Unable to complete the request.';
          feedback.focus?.();
        }
      } finally {
        password.value = '';
        pending = false;
        if (!signal.aborted) submitButton.disabled = false;
      }
    }

    form.addEventListener('submit', submit);
    onDispose(() => form.removeEventListener('submit', submit));
    return;
  }

  const message = status();
  message.textContent = 'Loading your session…';
  async function load() {
    try {
      const session = await client.getSession({ signal });
      if (signal.aborted) return;
      if (!session?.user) {
        navigate('/app/login');
        return;
      }
      const user = session.user;
      message.textContent = '';
      const identity = page.createElement('p');
      identity.textContent = 'Signed in as ' + user.username + ' (' + user.role + ')';
      const logout = page.createElement('button');
      logout.type = 'button';
      logout.textContent = 'Sign out';
      section.append(identity, logout);
      if (user.role === 'admin') {
        const link = page.createElement('a');
        link.href = '/app/statistics';
        link.textContent = 'Statistics';
        section.append(link);
      }
      async function signOut() {
        if (logout.disabled || signal.aborted) return;
        logout.disabled = true;
        try {
          await client.request('/api/accounts/logout', { method: 'POST', signal });
          if (signal.aborted) return;
          client.clearSession();
          navigate('/app/login');
        } catch (error) {
          if (!signal.aborted && error?.name !== 'AbortError') {
            message.textContent = error?.message || 'Unable to sign out.';
            logout.disabled = false;
          }
        }
      }
      logout.addEventListener('click', signOut);
      onDispose(() => logout.removeEventListener('click', signOut));
    } catch (error) {
      if (!signal.aborted && error?.name !== 'AbortError') {
        message.textContent = error?.message || 'Could not load your session.';
      }
    }
  }
  void load();
}
