import { createNavigator, showPlaceholder } from './navigate.js';
import { createHttpClient } from './requestJson.js';
import { renderAccounts } from './Accounts/showAccounts.js';

/**
 * Start the deliberately small application shell without taking ownership of SSR features.
 *
 * Feature navigation and session-aware screens are added separately after tested contracts.
 * This module only owns the initial DOM readiness message.
 *
 * @param {Document} page Native document; injectable for zero-dependency tests.
 * @returns {boolean} Whether the known shell root was available.
 */
export function startApp(page) {
  const root = page.getElementById('app-root');

  if (root === null) {
    return false;
  }

  const status = root.querySelector('[data-shell-status]');

  if (status !== null) {
    status.textContent = 'Application shell loaded. Existing sign-in remains available.';
  }

  return true;
}

export function startNavigation(page, win) {
  const outlet = page.getElementById('route-outlet');
  if (outlet === null) return null;
  const client = createHttpClient({ origin: win.location.origin });
  let navigator;
  navigator = createNavigator({ page, win, outlet, render(screen, lifecycle) {
    if (['/app', '/app/home', '/app/login', '/app/register'].includes(screen.path)) {
      renderAccounts(screen, lifecycle, { client, navigate: (path) => navigator.navigate(path) });
    } else {
      showPlaceholder(screen, lifecycle);
    }
  } });
  return navigator;
}

if (typeof document !== 'undefined') {
  startApp(document);
  startNavigation(document, window);
}
