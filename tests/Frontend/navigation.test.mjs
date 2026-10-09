import test from 'node:test';
import assert from 'node:assert/strict';
import { screenForPath, createNavigator } from '../../public/app/navigate.js';
test('unknown SPA routes are not API routes', () => {
  assert.equal(screenForPath('/api/session'), null);
  assert.equal(screenForPath('/app/unexpected').status, 404);
});

function browser() {
  const listeners = new Map();
  const win = {
    location: { pathname: '/app/login', origin: 'https://example.test', href: 'https://example.test/app/login' },
    history: { paths: [], pushState(_data, _title, path) {
      this.paths.push(path);
      win.location.pathname = path;
      win.location.href = win.location.origin + path;
    } },
    addEventListener(name, callback) { listeners.set(name, callback); },
    removeEventListener(name) { listeners.delete(name); },
    fire(name, event) { listeners.get(name)?.(event); },
  };
  const outlet = { replaceChildren() {} };
  const page = {};
  return { win, outlet, page, listeners };
}

test('navigation aborts and disposes old screens; browser back does not push history', () => {
  const { win, outlet, page, listeners } = browser();
  const activity = [];
  const navigation = createNavigator({ win, page, outlet, render(screen, lifecycle) {
    activity.push({ path: screen.path, signal: lifecycle.signal });
    lifecycle.onDispose(() => activity.push({ disposed: screen.path }));
  } });
  const initial = activity[0];
  assert.equal(initial.path, '/app/login');
  assert.equal(navigation.navigate('/app/register'), true);
  assert.equal(initial.signal.aborted, true);
  assert.deepEqual(win.history.paths, ['/app/register']);
  win.location.pathname = '/app/login';
  win.fire('popstate');
  assert.deepEqual(win.history.paths, ['/app/register']);
  assert.equal(activity.at(-1).path, '/app/login');
  navigation.stop();
  assert.equal(listeners.size, 0);
  assert.equal(activity.at(-1).disposed, '/app/login');
  assert.equal(navigation.navigate('/app'), false);
});

test('intercepts only same-origin unmodified SPA links', () => {
  const { win, page, outlet } = browser();
  const navigation = createNavigator({ win, page, outlet, render() {} });
  function click(href, extra = {}) {
    const event = { button: 0, defaultPrevented: false, ctrlKey: false, metaKey: false,
      altKey: false, shiftKey: false, target: { closest: () => ({
        href, target: '', hasAttribute: () => false,
      }) }, preventDefault() { this.defaultPrevented = true; }, ...extra };
    win.fire('click', event);
    return event.defaultPrevented;
  }
  assert.equal(click('https://foreign.test/app/register'), false);
  assert.equal(click('https://example.test/login'), false);
  assert.equal(click('https://example.test/app/register', { ctrlKey: true }), false);
  assert.equal(click('https://example.test/app/register'), true);
  navigation.stop();
});
