import test from 'node:test';
import assert from 'node:assert/strict';
import { startApp } from '../../public/app/startApp.js';

test('native ES module initializes the owned shell status without HTML injection', () => {
  const status = { textContent: 'Starting…' };
  const root = { querySelector: (selector) => {
    assert.equal(selector, '[data-shell-status]');
    return status;
  } };
  const page = { getElementById: (id) => {
    assert.equal(id, 'app-root');
    return root;
  } };

  assert.equal(startApp(page), true);
  assert.equal(status.textContent, 'Application shell loaded. Existing sign-in remains available.');
});

test('shell startup is safely inert without its root', () => {
  assert.equal(startApp({ getElementById: () => null }), false);
});
