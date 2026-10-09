import test from 'node:test';
import assert from 'node:assert/strict';
import { showPageB } from '../../public/app/Downloads/showPageB.js';

function make(tag) {
  return { tag, children: [], textContent: '', value: '',
    append(...items) { this.children.push(...items); },
    replaceChildren(...items) { this.children = items; },
    setAttribute(key, value) { this[key] = value; },
    focus() {},
  };
}

test('Page B records active visit and uses native CSRF form, not a JS blob download', async () => {
  const calls = [];
  const outlet = make('main');
  showPageB({ page: { createElement: make }, outlet,
    signal: new AbortController().signal }, {
    client: {
      request: async (path, options) => { calls.push([path, options.method]); return null; },
      getCsrf: async () => 'f'.repeat(64),
    },
    navigate() {},
  });
  await new Promise(setImmediate);
  const section = outlet.children[0];
  const form = section.children.find((child) => child.tag === 'form');
  assert.deepEqual(calls, [['/api/downloads/page/view', 'POST']]);
  assert.equal(form.method.toLowerCase(), 'post');
  assert.equal(form.action, '/api/downloads/sample');
  const token = form.children.find((child) => child.name === 'csrf_token');
  assert.equal(token.type, 'hidden');
  assert.equal(token.value, 'f'.repeat(64));
  assert.equal(form.children.find((child) => child.tag === 'button').textContent, 'Download');
});
