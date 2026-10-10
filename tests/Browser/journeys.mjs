import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { chromium } from 'playwright';

const origin = process.argv[2];
assert.ok(origin?.startsWith('http://127.0.0.1:'), 'Browser acceptance uses only isolated local server');
const browser = await chromium.launch({
  executablePath: '/usr/bin/google-chrome',
  headless: true,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const errors = [];
const network = [];
async function context() {
  const session = await browser.newContext({ acceptDownloads: true });
  const page = await session.newPage();
  page.on('pageerror', (error) => errors.push(String(error)));
  page.on('request', (req) => { if (req.url().includes('/api/')) network.push('REQ ' + req.method() + ' ' + req.url()); });
  page.on('requestfailed', (req) => network.push('FAIL ' + req.url() + ' ' + req.failure()?.errorText));
  page.on('response', (resp) => { if (resp.url().includes('/api/')) network.push('RES ' + resp.status() + ' ' + resp.url()); });
  return { session, page };
}
async function visit(page, path, heading) {
  await page.goto(origin + path);
  const aliases = {
    '/': '/app/home', '/login': '/app/login', '/register': '/app/register',
    '/page-a': '/app/page-a', '/page-b': '/app/page-b',
    '/statistics': '/app/statistics', '/reports': '/app/reports',
  };
  if (Object.hasOwn(aliases, path)) {
    assert.equal(new URL(page.url()).pathname, aliases[path], 'Cutover redirect failed: ' + path);
  }
  await page.getByRole('heading', { name: heading }).waitFor();
}
async function login(page, username, password) {
  await visit(page, '/login', 'Sign in');
  await page.getByLabel('Username').fill(username);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.waitForURL(origin + '/app/home');
  await page.getByText('Signed in as ' + username, { exact: false }).waitFor();
}
try {
  const { session, page } = await context();
  await visit(page, '/register', 'Create account');
  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('strong-password');
  await page.getByRole('button', { name: 'Register' }).click();
  await page.getByText('Registration complete.', { exact: false }).waitFor({ timeout: 7000 })
    .catch(async () => { throw new Error('Registration UI state: ' +
      await page.locator('#route-outlet').innerText() + ' | network: ' + network.join(' | ') + ' | page errors: ' + errors.join('; ')); });
  await visit(page, '/login', 'Sign in');
  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('bad-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.getByRole('alert').getByText('Invalid username or password.').waitFor();
  assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('role')), 'alert');

  await login(page, 'browser-user', 'strong-password');
  await visit(page, '/page-a', 'Page A');
  const cow = page.getByRole('button', { name: 'Buy a cow' });
  await cow.click();
  await page.getByText('thankYou', { exact: true }).waitFor();
  assert.equal(await cow.isHidden(), true);
  await visit(page, '/page-b', 'Page B');
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'Download' }).click(),
  ]);
  assert.equal(download.suggestedFilename(), 'sample.exe');
  const bytes = await readFile(await download.path());
  assert.equal(bytes.length, 1024);
  assert.equal(createHash('sha256').update(bytes).digest('hex'),
    '31a97b0f71277adbac9bfde08c935c3e97b0bfa61d59c807c0a900660080cb80');

  await visit(page, '/statistics', 'Activity statistics');
  await page.getByRole('alert').getByText('Forbidden.').waitFor();
  await visit(page, '/', 'Home');
  await page.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(origin + '/app/login');
  await page.goto(origin + '/statistics');
  await page.waitForURL(origin + '/app/login');
  await session.close();

  const admin = await context();
  await login(admin.page, 'review-admin', 'review-secret');
  await visit(admin.page, '/statistics', 'Activity statistics');
  await admin.page.getByRole('table').waitFor();
  await admin.page.getByLabel('User').fill('browser-user');
  await admin.page.evaluate(() => { window.__spaDocumentSentinel = 'preserved'; });
  await admin.page.getByRole('button', { name: 'Apply filters' }).click();
  await admin.page.waitForFunction(() => location.pathname === '/app/statistics'
    && new URLSearchParams(location.search).get('user') === 'browser-user', null, { timeout: 3500 })
    .catch(async () => { throw new Error('SPA GET filter navigation failed: URL=' + admin.page.url()
      + '; pageErrors=' + errors.join(' | ') + '; outlet='
      + await admin.page.locator('#route-outlet').innerText()); });
  assert.equal(await admin.page.evaluate(() => window.__spaDocumentSentinel), 'preserved',
    'Filter submission must not reload the SPA document');
  await admin.page.evaluate(() => history.back());
  await admin.page.waitForFunction(() => location.pathname === '/app/statistics' && location.search === '');
  await admin.page.evaluate(() => history.forward());
  await admin.page.waitForFunction(() => new URLSearchParams(location.search).get('user') === 'browser-user');
  assert.equal(await admin.page.evaluate(() => window.__spaDocumentSentinel), 'preserved');
  await admin.page.getByRole('table').getByText('browser-user').first().waitFor();
  await admin.page.goto(origin + '/reports');
  assert.equal(new URL(admin.page.url()).pathname, '/app/reports');
  await admin.page.getByRole('heading', { name: 'Daily activity reports' }).waitFor();
  const reportChart = admin.page.locator('svg[role="img"]');
  await reportChart.waitFor();
  const legend = await reportChart.locator('text').allTextContents();
  assert.deepEqual(legend.filter((label) => [
    'Page A views', 'Page B views', 'Buy cow clicks', 'Download clicks',
  ].includes(label)), ['Page A views', 'Page B views', 'Buy cow clicks', 'Download clicks']);
  const reportTable = admin.page.getByRole('table');
  await reportTable.waitFor();
  assert.deepEqual(await reportTable.locator('thead th').allTextContents(), [
    'UTC date', 'Page A views', 'Page B views', 'Buy cow clicks', 'Download clicks',
  ]);
  assert.equal(errors.length, 0, errors.join('\n'));
  await admin.session.close();
  console.log('PLAYWRIGHT BROWSER ACCEPTANCE: PASS; auth, audit flows, binary, roles, filtered statistics, report SVG');
} finally {
  await browser.close();
}
