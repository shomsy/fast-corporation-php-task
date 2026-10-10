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
  // Actual browser validation: UTF-8 byte length must not bypass 12 visible characters.
  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('🔒'.repeat(11));
  await page.getByRole('button', { name: 'Register' }).click();
  await page.getByRole('alert').getByText('Use at least 12 characters for your password.').waitFor();

  await page.getByLabel('Username').fill('bad name');
  await page.getByLabel('Password').fill('strong-password');
  await page.getByRole('button', { name: 'Register' }).click();
  await page.getByRole('alert').getByText('Use 1–64 characters for your username.', { exact: false }).waitFor();

  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('strong-password');
  await page.getByRole('button', { name: 'Register' }).click();
  await page.getByRole('heading', { name: 'Registration successful' }).waitFor({ timeout: 7000 });
  assert.equal(await page.locator('#route-outlet form').count(), 0, 'Successful registration must remove the form');
  await page.getByText('You are not signed in automatically.', { exact: false }).waitFor();
  await page.getByRole('link', { name: 'Sign in to your account' }).waitFor();
  await visit(page, '/register', 'Create account');
  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('strong-password');
  await page.getByRole('button', { name: 'Register' }).click();
  await page.getByRole('alert').getByText('That username is already in use. Please choose another.').waitFor();
  await visit(page, '/login', 'Sign in');
  await page.getByLabel('Username').fill('browser-user');
  await page.getByLabel('Password').fill('bad-password');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await page.getByRole('alert').getByText('Incorrect username or password. Please try again.').waitFor();
  assert.equal(await page.evaluate(() => document.activeElement?.getAttribute('role')), 'alert');

  await login(page, 'browser-user', 'strong-password');
  const shellNav = page.locator('[data-app-nav]');
  await shellNav.getByRole('button', { name: 'Sign out' }).waitFor();
  assert.equal(await shellNav.locator('a[href="/app/login"]').isHidden(), true);
  assert.equal(await shellNav.locator('a[href="/app/register"]').isHidden(), true);
  assert.equal(await shellNav.locator('a[href="/app/statistics"]').isHidden(), true);
  assert.equal(await shellNav.locator('a[href="/app/reports"]').isHidden(), true);
  const usersResponse = await page.evaluate(async () =>
    (await fetch('/api/accounts/users', { credentials: 'same-origin' })).status);
  assert.equal(usersResponse, 403, 'A regular user cannot list administrator-managed accounts');
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

  for (const [path, heading] of [['/statistics', 'Activity statistics'], ['/reports', 'Daily activity reports']]) {
    await visit(page, path, heading);
    await page.getByRole('heading', { name: 'Access denied' }).waitFor();
    await page.locator('.access-denied-icon').getByText('⛔').waitFor();
    assert.equal(await page.locator('#route-outlet form').count(), 0, 'Denied view must not contain filters');
  }
  await visit(page, '/', 'Home');
  await shellNav.getByRole('button', { name: 'Sign out' }).click();
  await page.waitForURL(origin + '/app/login');
  await shellNav.getByRole('link', { name: 'Sign in', exact: true }).waitFor();
  assert.equal(await shellNav.locator('button[data-session-nav="logout"]').isHidden(), true);
  await page.goto(origin + '/statistics');
  await page.waitForURL(origin + '/app/login');
  await session.close();

  const admin = await context();
  await login(admin.page, 'review-admin', 'review-secret');
  const adminNav = admin.page.locator('[data-app-nav]');
  await adminNav.getByRole('link', { name: 'Statistics' }).waitFor();
  await adminNav.getByRole('link', { name: 'Reports' }).waitFor();
  assert.equal(await adminNav.locator('a[href="/app/login"]').isHidden(), true);
  assert.equal(await adminNav.locator('a[href="/app/register"]').isHidden(), true);
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
  await admin.page.goto(origin + '/app/statistics?action=unknown-action');
  await admin.page.getByRole('alert').getByText('We could not load those results. Check your filters or clear them and try again.').waitFor();
  await admin.page.goto(origin + '/app/reports?from=2026-10-02&to=2026-10-01');
  await admin.page.getByRole('alert').getByText('Enter valid dates (YYYY-MM-DD). The start date must not be after the end date.').waitFor();
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
  await visit(admin.page, '/', 'Home');
  await admin.page.getByRole('heading', { name: 'Manage users' }).waitFor();
  const managed = admin.page.locator('.user-row').filter({ hasText: 'browser-user' });
  await managed.getByRole('button', { name: 'Make browser-user an administrator' }).click();
  await managed.getByRole('button', { name: 'Confirm access' }).click();
  await admin.page.getByText('browser-user now has administrator access.').waitFor();
  await managed.getByText('Administrator').waitFor();
  const upgraded = await context();
  await login(upgraded.page, 'browser-user', 'strong-password');
  await upgraded.page.locator('[data-app-nav]').getByRole('link', { name: 'Statistics' }).waitFor();
  await upgraded.session.close();
  assert.equal(errors.length, 0, errors.join('\n'));
  await admin.session.close();
  console.log('PLAYWRIGHT BROWSER ACCEPTANCE: PASS; auth, audit flows, binary, roles, filtered statistics, report SVG');
} finally {
  await browser.close();
}
