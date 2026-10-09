const titles = new Map([
  ['/app', 'Home'],
  ['/app/login', 'Sign in'],
  ['/app/register', 'Register'],
  ['/app/home', 'Home'],
  ['/app/page-a', 'Page A'],
  ['/app/page-b', 'Page B'],
  ['/app/statistics', 'Statistics'],
  ['/app/reports', 'Reports'],
]);

export function screenForPath(path) {
  if (path !== '/app' && !path.startsWith('/app/')) return null;
  return titles.has(path) ? { path, title: titles.get(path), status: 200 }
    : { path, title: 'Page not found', status: 404 };
}

function showPlaceholder(screen, { page, outlet }) {
  const section = page.createElement('section');
  const heading = page.createElement('h2');
  heading.textContent = screen.title;
  heading.tabIndex = -1;
  const message = page.createElement('p');
  message.textContent = screen.status === 404
    ? 'This page does not exist.'
    : 'This screen is being migrated. The original workflow remains available.';
  section.append(heading, message);
  outlet.replaceChildren(section);
  heading.focus?.();
}

/**
 * The single SPA navigation owner. Each screen has a cancellable lifetime.
 * No API request, business state or untrusted HTML belongs in the navigator.
 */
export function createNavigator({ win, page, outlet, render = showPlaceholder }) {
  let active = null;
  let stopped = false;

  function mount(path) {
    const screen = screenForPath(path);
    if (screen === null || stopped) return false;
    active?.controller.abort();
    active?.dispose.forEach((dispose) => dispose());
    const controller = new AbortController();
    const dispose = [];
    active = { controller, dispose };
    render(screen, {
      page, outlet, signal: controller.signal,
      onDispose(callback) { dispose.push(callback); },
    });
    return true;
  }

  function navigate(path) {
    if (stopped || !screenForPath(path)) return false;
    if (path === win.location.pathname) return true;
    win.history.pushState(null, '', path);
    return mount(path);
  }

  function onPopstate() { mount(win.location.pathname); }

  function onClick(event) {
    if (event.defaultPrevented || event.button !== 0 || event.ctrlKey
      || event.metaKey || event.altKey || event.shiftKey) return;
    const anchor = event.target?.closest?.('a[href]');
    if (!anchor || anchor.hasAttribute('download') || (anchor.target && anchor.target !== '_self')) return;
    const url = new URL(anchor.href, win.location.href);
    if (url.origin !== win.location.origin || url.search || url.hash
      || !screenForPath(url.pathname)) return;
    event.preventDefault();
    navigate(url.pathname);
  }

  win.addEventListener('popstate', onPopstate);
  win.addEventListener('click', onClick);
  mount(win.location.pathname);

  return {
    navigate,
    stop() {
      if (stopped) return;
      stopped = true;
      win.removeEventListener('popstate', onPopstate);
      win.removeEventListener('click', onClick);
      active?.controller.abort();
      active?.dispose.forEach((dispose) => dispose());
      active = null;
    },
  };
}
