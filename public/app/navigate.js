const titles = new Map([
  ['/app', 'Home'], ['/app/login', 'Sign in'], ['/app/register', 'Register'],
  ['/app/home', 'Home'], ['/app/page-a', 'Page A'], ['/app/page-b', 'Page B'],
  ['/app/statistics', 'Statistics'], ['/app/reports', 'Reports'],
]);

export function screenForPath(path) {
  if (path !== '/app' && !path.startsWith('/app/')) return null;
  return titles.has(path) ? { path, title: titles.get(path), status: 200 }
    : { path, title: 'Page not found', status: 404 };
}

export function showPlaceholder(screen, { page, outlet }) {
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = screen.title;
  heading.tabIndex = -1;
  const message = page.createElement('p');
  message.textContent = 'This page does not exist.';
  section.append(heading, message);
  outlet.replaceChildren(section);
  heading.focus?.();
}

/** One navigation owner: History, filter query, screen cancellation, focus and listeners. */
export function createNavigator({ win, page, outlet, render = showPlaceholder }) {
  let active = null;
  let stopped = false;

  function address() { return win.location.pathname + (win.location.search || ''); }

  function dispose() {
    active?.controller.abort();
    for (const cleanup of active?.dispose || []) cleanup();
    active = null;
  }

  function mount() {
    if (stopped) return false;
    const screen = screenForPath(win.location.pathname);
    if (screen === null) return false;
    dispose();
    const controller = new AbortController();
    const callbacks = [];
    active = { controller, dispose: callbacks };
    page.title = screen.title + ' — Fast Corporation';
    page.querySelectorAll?.('nav[data-app-nav] a[href]').forEach((anchor) => {
      if (new URL(anchor.href, win.location.href).pathname === screen.path) anchor.setAttribute('aria-current', 'page');
      else anchor.removeAttribute('aria-current');
    });
    render(screen, { page, outlet, signal: controller.signal, onDispose(fn) { callbacks.push(fn); } });
    return true;
  }

  function navigate(destination, { replace = false } = {}) {
    if (stopped || typeof destination !== 'string') return false;
    const target = new URL(destination, win.location.href);
    if (target.origin !== win.location.origin || target.hash || !screenForPath(target.pathname)) return false;
    const next = target.pathname + target.search;
    if (next === address()) return true;
    if (replace) win.history.replaceState(null, '', next);
    else win.history.pushState(null, '', next);
    const mounted = mount();
    if (mounted) win.scrollTo?.(0, 0);
    return mounted;
  }

  function onPopstate() { mount(); }
  function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey ||
      event.metaKey || event.altKey || event.shiftKey) return;
    const anchor = event.target?.closest?.('a[href]');
    if (!anchor || anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;
    const url = new URL(anchor.href, win.location.href);
    if (url.origin !== win.location.origin || url.hash || !screenForPath(url.pathname)) return;
    event.preventDefault();
    navigate(url.pathname + url.search);
  }

  function onSubmit(event) {
    if (event.defaultPrevented) return;
    const form = event.target;
    if (!form || String(form.getAttribute?.('method') || form.method).toUpperCase() !== 'GET') return;
    // A form control named 'action' shadows form.action in browsers. Read the HTML attribute.
    const url = new URL(form.getAttribute?.('action') || win.location.href, win.location.href);
    if (url.origin !== win.location.origin || !screenForPath(url.pathname)) return;
    const params = new URLSearchParams(new FormData(form));
    for (const [key, value] of [...params]) if (!value.trim()) params.delete(key);
    event.preventDefault();
    navigate(url.pathname + (params.size ? '?' + params : ''));
  }

  win.addEventListener('popstate', onPopstate);
  win.addEventListener('click', onClick);
  win.addEventListener('submit', onSubmit);
  mount();
  return {
    navigate,
    stop() {
      if (stopped) return;
      stopped = true;
      win.removeEventListener('popstate', onPopstate);
      win.removeEventListener('click', onClick);
      win.removeEventListener('submit', onSubmit);
      dispose();
    },
  };
}
