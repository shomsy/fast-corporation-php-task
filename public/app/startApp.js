import { createNavigator } from './navigate.js';

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
  return outlet === null ? null : createNavigator({ page, win, outlet });
}

if (typeof document !== 'undefined') {
  startApp(document);
  startNavigation(document, window);
}
