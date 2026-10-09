import test from 'node:test';
import assert from 'node:assert/strict';
import { renderAccounts } from '../../public/app/Accounts/showAccounts.js';

function element(tag) {
  const events = new Map();
  return {
    tag, textContent: '', children: [], value: '', disabled: false,
    append(...items) { this.children.push(...items); },
    replaceChildren(...items) { this.children = items; },
    addEventListener(type, listener) { events.set(type, listener); },
    removeEventListener(type) { events.delete(type); },
    setAttribute(name, value) { this[name] = value; },
    focus() { this.focused = true; },
    dispatch(type) {
      const result = events.get(type)?.({ preventDefault() {} });
      return result;
    },
    events,
  };
}

function setup(path, client) {
  const outlet = element('main');
  const cleanup = [];
  const visited = [];
  const page = { createElement: element };
  const controller = new AbortController();
  renderAccounts({ path, title: 'Accounts' },
    { page, outlet, signal: controller.signal, onDispose: (f) => cleanup.push(f) },
    { client, navigate: (path) => visited.push(path) });
  return { outlet, cleanup, visited, controller };
}

test('home loads current server identity and safely renders a hostile username as plain text', async () => {
  const name = '<img src=x onerror=alert(1)>';
  const s = setup('/app/home', { getSession: async () => ({ user: { username: name, role: 'user' } }) });
  await new Promise(setImmediate);
  const text = JSON.stringify(s.outlet, (key, value) => key === 'events' ? undefined : value);
  assert.ok(text.includes(name));
  assert.ok(!text.includes('innerHTML'));
});

test('login submits once, applies rotated token and follows app navigation', async () => {
  let token = null;
  let calls = 0;
  const s = setup('/app/login', {
    request: async (_path, options) => {
      calls++;
      assert.equal(options.method, 'POST');
      return { user: { username: 'alice', role: 'user' }, csrfToken: 'a'.repeat(64) };
    },
    setCsrfToken: (value) => { token = value; },
  });
  const form = s.outlet.children[0].children.find((child) => child.tag === 'form');
  const inputs = form.children.filter((child) => child.tag === 'input');
  inputs[0].value = 'alice';
  inputs[1].value = 'strong-password';
  await form.dispatch('submit');
  assert.equal(calls, 1);
  assert.equal(token, 'a'.repeat(64));
  assert.deepEqual(s.visited, ['/app/home']);
  for (const dispose of s.cleanup) dispose();
  assert.equal(form.events.size, 0);
});
