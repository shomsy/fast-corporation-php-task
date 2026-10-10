import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { renderAccounts } from '../../public/app/Accounts/showAccounts.js';
import { showPageA } from '../../public/app/CowOffer/showPageA.js';
import { showActivity } from '../../public/app/ActivityTracking/showActivity.js';

function node(tag) {
  const listeners = new Map();
  return {
    tag, children: [], textContent: '', value: '', disabled: false, hidden: false, events: listeners,
    append(...children) { this.children.push(...children); },
    replaceChildren(...children) { this.children = children; },
    setAttribute(name, value) { this[name] = String(value); },
    addEventListener(type, listener) { listeners.set(type, listener); },
    removeEventListener(type) { listeners.delete(type); },
    async dispatch(type) { await listeners.get(type)?.({ preventDefault() {} }); },
    focus() {
      if (['input', 'button', 'a', 'select'].includes(tag) || this.tabIndex === -1) this.focused = true;
    },
  };
}

test('validation error is announced and programmatically focusable', async () => {
  const outlet = node('main');
  const controller = new AbortController();
  renderAccounts({ path: '/app/login' }, {
    page: { createElement: node }, outlet, signal: controller.signal, onDispose() {},
  }, {
    client: { request: async () => { throw { message: '<svg onload=alert(1)>' }; } },
    navigate() {},
  });
  const form = outlet.children[0].children.find((child) => child.tag === 'form');
  await form.dispatch('submit');
  const alert = form.children.find((child) => child.tag === 'p');
  assert.equal(alert.textContent, '<svg onload=alert(1)>');
  assert.equal(alert.role, 'alert');
  assert.equal(alert.focused, true);
});

test('failed Cow Offer click is focusable; stale response after route disposal is inert', async () => {
  const outlet = node('main');
  const lifetime = new AbortController();
  let deliver;
  const deferred = new Promise((resolve) => { deliver = resolve; });
  showPageA({ page: { createElement: node }, outlet, signal: lifetime.signal, onDispose() {} }, {
    client: { request: async () => deferred }, navigate() {},
  });
  lifetime.abort();
  deliver(null);
  await new Promise(setImmediate);
  assert.equal(outlet.children[0].children.some((child) => child.tag === 'button'), false);

  const active = node('main');
  showPageA({ page: { createElement: node }, outlet: active,
    signal: new AbortController().signal, onDispose() {} }, {
    client: { request: async (path) => path.endsWith('/view') ? null :
      Promise.reject({ message: 'The server could not complete this request.' }) },
    navigate() {},
  });
  await new Promise(setImmediate);
  const section = active.children[0];
  const buy = section.children.find((child) => child.tag === 'button');
  await buy.dispatch('click');
  const feedback = section.children.find((child) => child.tag === 'p');
  assert.equal(feedback.focused, true);
  assert.equal(feedback.role, 'alert');
});

test('admin denial is announced and focusable', async () => {
  const outlet = node('main');
  showActivity({ path: '/app/reports' }, {
    page: { createElement: node }, outlet, signal: new AbortController().signal, onDispose() {},
  }, {
    client: { request: async () => { throw { status: 403 }; } }, navigate() {},
  });
  await new Promise(setImmediate);
  const feedback = outlet.children[0].children.find((child) => child.tag === 'p');
  assert.equal(feedback.textContent, "You do not have permission to view this information.");
  assert.equal(feedback.focused, true);
});

test('all SPA feature files avoid executable HTML insertion', () => {
  const base = new URL('../../public/app/', import.meta.url);
  function files(directory) {
    for (const item of readdirSync(directory, { withFileTypes: true })) {
      const path = join(directory, item.name);
      if (item.isDirectory()) files(path);
      else if (item.name.endsWith('.js')) {
        const source = readFileSync(path, 'utf8');
        assert.doesNotMatch(source, /\.innerHTML\s*=|\.outerHTML\s*=|insertAdjacentHTML\s*\(|document\.write\s*\(/,
          'Unsafe HTML insertion in ' + path);
      }
    }
  }
  files(base.pathname);
});

function luminance(hex) {
  const rgb = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
    .map((c) => c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4);
  return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
}
test('actual CSS text, button, error and focus colors retain adequate surface contrast', () => {
  const css = readFileSync(new URL('../../public/assets/app.css', import.meta.url), 'utf8');
  function color(name) {
    const declaration = css.match(new RegExp('--' + name + '-color:\\s*(#[a-fA-F0-9]{6})\\s*;'));
    assert.ok(declaration, 'Missing actual CSS color: ' + name);
    assert.ok(css.includes('var(--' + name + '-color)'), 'Color must be consumed by the stylesheet: ' + name);
    return declaration[1];
  }
  const surface = luminance(color('surface'));
  for (const name of ['text', 'button', 'error', 'focus']) {
    const foreground = luminance(color(name));
    const ratio = (Math.max(surface, foreground) + 0.05) / (Math.min(surface, foreground) + 0.05);
    assert.ok(ratio >= (name === 'focus' ? 3 : 4.5), name + ': ' + ratio.toFixed(2));
  }
});


test('repeated submit while pending never duplicates login and native labels identify inputs', async () => {
  const outlet = node('main');
  let complete;
  let calls = 0;
  const pending = new Promise((resolve) => { complete = resolve; });
  const visited = [];
  renderAccounts({ path: '/app/login' }, {
    page: { createElement: node }, outlet, signal: new AbortController().signal, onDispose() {},
  }, {
    client: {
      request: async () => { calls++; return pending; },
      setCsrfToken() {},
    },
    navigate: (path) => visited.push(path),
  });
  const form = outlet.children[0].children.find((child) => child.tag === 'form');
  const fields = form.children.filter((child) => child.tag === 'input');
  const labels = form.children.filter((child) => child.tag === 'label');
  assert.deepEqual(labels.map((label) => label.htmlFor), fields.map((input) => input.id));
  const first = form.dispatch('submit');
  await form.dispatch('submit');
  assert.equal(calls, 1);
  complete({ user: { username: 'test', role: 'user' }, csrfToken: 'e'.repeat(64) });
  await first;
  assert.deepEqual(visited, ['/app/home']);
});

test('expired session on Page A navigates to login without exposing private data', async () => {
  const visited = [];
  const outlet = node('main');
  showPageA({ page: { createElement: node }, outlet,
    signal: new AbortController().signal, onDispose() {} }, {
    client: { request: async () => { throw { status: 401 }; } },
    navigate: (path) => visited.push(path),
  });
  await new Promise(setImmediate);
  assert.deepEqual(visited, ['/app/login']);
  assert.equal(outlet.children[0].children.some((child) => child.tag === 'button'), false);
});
