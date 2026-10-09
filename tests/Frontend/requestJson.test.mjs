import test from 'node:test';
import assert from 'node:assert/strict';
import { createHttpClient, HttpError } from '../../public/app/requestJson.js';

const response = (status, body, extra = {}) => ({
  ok: status >= 200 && status < 300, status,
  headers: { get(key) { return extra[key.toLowerCase()] ?? 'application/json'; } },
  async json() { return body; },
});

test('session bootstrap uses same-origin cookie and refuses cross-origin paths', async () => {
  const calls = [];
  const client = createHttpClient({
    origin: 'https://example.test',
    fetchImpl: async (path, options) => {
      calls.push({ path, options });
      return response(200, { user: null });
    },
  });
  assert.deepEqual(await client.getSession(), { user: null });
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(calls[0].options.cache, 'no-store');
  await assert.rejects(() => client.request('https://evil.test/'), /same-origin/);
  assert.equal(calls.length, 1);
});

test('POST bootstraps CSRF once, sends JSON header and never blindly retries on forbidden', async () => {
  const calls = [];
  const client = createHttpClient({
    origin: 'https://example.test',
    fetchImpl: async (path, options) => {
      calls.push({ path, options });
      if (path.endsWith('/api/csrf')) return response(200, { csrfToken: 'a'.repeat(64) });
      return response(403, { error: { code: 'csrf_denied', message: 'Forbidden' } });
    },
  });
  await assert.rejects(() => client.request('/api/accounts/login', {
    method: 'POST', body: { username: 'alice', password: 'test-password' },
  }), (error) => error instanceof HttpError && error.status === 403);
  assert.equal(calls.length, 2);
  assert.equal(calls[1].options.headers['X-CSRF-Token'], 'a'.repeat(64));
  assert.equal(calls[1].options.credentials, 'same-origin');
  assert.equal(calls[1].options.redirect, 'error');
  assert.equal(calls[1].options.body, JSON.stringify({ username: 'alice', password: 'test-password' }));
});

test('safe structured status, Retry-After, request ID and aborted navigation are preserved', async () => {
  for (const status of [401, 403, 419, 422, 429, 500]) {
    const client = createHttpClient({
      origin: 'https://example.test',
      fetchImpl: async () => response(status, { error: { code: 'not_allowed', message: 'Safe message' } },
        { 'x-request-id': 'request-123', 'retry-after': '60' }),
    });
    await assert.rejects(() => client.request('/api/session'), (error) => {
      assert.equal(error.status, status);
      assert.equal(error.requestId, 'request-123');
      if (status === 429) assert.equal(error.retryAfter, 60);
      return true;
    });
  }
  const cancel = new AbortController();
  cancel.abort();
  const client = createHttpClient({
    origin: 'https://example.test',
    fetchImpl: async (_path, options) => {
      assert.equal(options.signal.aborted, true);
      throw new DOMException('Cancelled', 'AbortError');
    },
  });
  await assert.rejects(() => client.request('/api/session', { signal: cancel.signal }), { name: 'AbortError' });
});
