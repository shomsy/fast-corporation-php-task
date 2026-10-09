import test from 'node:test';
import assert from 'node:assert/strict';
import { showPageA } from '../../public/app/CowOffer/showPageA.js';

function element(tag) {
  const events = new Map();
  return { tag, textContent: '', children: [], hidden: false, disabled: false, events,
    append(...nodes) { this.children.push(...nodes); },
    replaceChildren(...nodes) { this.children = nodes; },
    addEventListener(type, callback) { events.set(type, callback); },
    removeEventListener(type) { events.delete(type); },
    setAttribute(name, value) { this[name] = value; },
    focus() {},
    async click() { await events.get('click')?.({ preventDefault() {} }); },
  };
}

test('Page A records one view per mount and each accepted click has transient thankYou', async () => {
  const requests = [];
  const root = element('main');
  const cleanup = [];
  showPageA({
    page: { createElement: element }, outlet: root, signal: new AbortController().signal,
    onDispose: (handler) => cleanup.push(handler),
  }, {
    client: { request: async (path, options) => {
      requests.push({ path, options });
      return path.endsWith('/buy') ? { outcome: 'thankYou' } : null;
    } },
    navigate: () => {},
  });
  await new Promise(setImmediate);
  const section = root.children[0];
  const button = section.children.find((node) => node.tag === 'button');
  const feedback = section.children.find((node) => node.role === 'status');
  assert.equal(requests[0].path, '/api/cow-offer/view');
  await button.click();
  assert.equal(requests[1].path, '/api/cow-offer/buy');
  assert.equal(button.hidden, true);
  assert.equal(feedback.textContent, 'thankYou');
  cleanup.forEach((f) => f());
  assert.equal(button.events.size, 0);
});
