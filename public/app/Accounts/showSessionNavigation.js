/**
 * Server-backed account navigation for the shared SPA shell.
 * Local login/logout results may update presentation, but PHP still authorizes all requests.
 */
export function showSessionNavigation(page, { client, navigate }) {
  const nav = page.querySelector?.('nav[data-app-nav]');
  if (!nav) return { setUser() {}, refresh() {}, stop() {} };

  const guestLinks = [...nav.querySelectorAll('[data-session-nav="guest"]')];
  const adminLinks = [...nav.querySelectorAll('[data-session-nav="admin"]')];
  const logout = nav.querySelector('[data-session-nav="logout"]');
  const feedback = page.createElement('span');
  feedback.className = 'nav-feedback';
  feedback.setAttribute('role', 'alert');
  feedback.setAttribute('aria-live', 'polite');
  nav.append(feedback);
  const controller = new AbortController();
  let revision = 0;
  let pending = false;

  function setVisible(user) {
    const authenticated = typeof user?.username === 'string'
      && ['user', 'admin'].includes(user.role);
    for (const item of guestLinks) item.hidden = authenticated;
    for (const item of adminLinks) item.hidden = !(authenticated && user.role === 'admin');
    if (logout) logout.hidden = !authenticated;
  }

  function setUser(user) {
    if (controller.signal.aborted) return;
    revision++;
    feedback.textContent = '';
    setVisible(user);
  }

  async function refresh() {
    const current = ++revision;
    try {
      const session = await client.getSession({ signal: controller.signal });
      if (!controller.signal.aborted && current === revision) setVisible(session?.user ?? null);
    } catch (error) {
      if (!controller.signal.aborted && current === revision && error?.name !== 'AbortError') {
        feedback.textContent = 'We could not check your session. Refresh the page to try again.';
      }
    }
  }

  async function signOut() {
    if (pending || controller.signal.aborted) return;
    pending = true;
    if (logout) logout.disabled = true;
    feedback.textContent = '';
    try {
      await client.request('/api/accounts/logout', { method: 'POST', signal: controller.signal });
      if (controller.signal.aborted) return;
      client.clearSession();
      setUser(null);
      navigate('/app/login');
    } catch (error) {
      if (!controller.signal.aborted && error?.name !== 'AbortError') {
        if (error?.status === 401) {
          client.clearSession();
          setUser(null);
          navigate('/app/login');
        } else {
          feedback.textContent = error?.message || 'We could not sign you out. Please try again.';
        }
      }
    } finally {
      pending = false;
      if (!controller.signal.aborted && logout) logout.disabled = false;
    }
  }

  logout?.addEventListener('click', signOut);
  void refresh();
  return {
    setUser,
    refresh,
    stop() {
      controller.abort();
      logout?.removeEventListener('click', signOut);
      feedback.remove();
    },
  };
}
