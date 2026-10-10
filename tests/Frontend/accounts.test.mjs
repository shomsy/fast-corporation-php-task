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

test('login accepts historical usernames beyond registration length without truncating credentials', async () => {
  const historicalName = 'historical-account-'.repeat(5);
  let submitted;
  const login = setup('/app/login', {
    request: async (_path, { body }) => {
      submitted = body.username;
      return { user: { username: historicalName, role: 'user' }, csrfToken: 'a'.repeat(64) };
    },
    setCsrfToken() {},
  });
  const loginForm = login.outlet.children[0].children.find((child) => child.tag === 'form');
  const username = loginForm.children.find((child) => child.name === 'username');
  assert.ok(historicalName.length > 64);
  assert.equal(username.maxLength, 4096);
  username.value = historicalName;
  await loginForm.dispatch('submit');
  assert.equal(submitted, historicalName);
  assert.deepEqual(login.visited, ['/app/home']);

  const registration = setup('/app/register', {});
  const registerForm = registration.outlet.children[0].children.find((child) => child.tag === 'form');
  assert.equal(registerForm.children.find((child) => child.name === 'username').maxLength, 64);
});

test('registration displays a plain-language password rule and alerts on rejected submission', async () => {
  const s = setup('/app/register', {
    request: async () => {
      throw { status: 422, code: 'registration_rejected',
        message: 'Use at least 12 characters for your password.' };
    },
  });
  const form = s.outlet.children[0].children.find((child) => child.tag === 'form');
  const input = form.children.find((child) => child.name === 'password');
  const hint = form.children.find((child) => child.id === 'account-password-hint');
  assert.equal(input['aria-describedby'], 'account-password-hint');
  assert.equal(hint.textContent, 'Use at least 12 characters for your password.');
  await form.dispatch('submit');
  const feedback = form.children.find((child) => child.role === 'alert');
  assert.equal(feedback.textContent, 'Use at least 12 characters for your password.');
  assert.equal(feedback.focused, true);
});
