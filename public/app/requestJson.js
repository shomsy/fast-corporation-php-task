export class HttpError extends Error {
  constructor(status, message, { code = 'request_failed', requestId = null, retryAfter = null } = {}) {
    super(message);
    this.name = 'HttpError';
    this.status = status;
    this.code = code;
    this.requestId = requestId;
    this.retryAfter = retryAfter;
  }
}

/**
 * One same-origin JSON transport owner; cookie and CSRF stay under PHP authority.
 * No DOM access, local storage, JWT or automatic mutation retry.
 */
export function createHttpClient({ fetchImpl = fetch, origin } = {}) {
  if (!origin) throw new TypeError('An application origin is required.');
  const base = new URL(origin);
  let csrfToken = null;

  function endpoint(path) {
    if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) {
      throw new TypeError('Requests must use a same-origin absolute path.');
    }
    const url = new URL(path, base);
    if (url.origin !== base.origin || url.username || url.password || !url.pathname.startsWith('/api/')) {
      throw new TypeError('Requests must use a same-origin API endpoint.');
    }
    return url;
  }

  async function request(path, { method = 'GET', body, signal } = {}) {
    const url = endpoint(path);
    const verb = method.toUpperCase();
    const unsafe = !['GET', 'HEAD', 'OPTIONS'].includes(verb);
    const headers = { Accept: 'application/json' };

    if (unsafe) {
      if (csrfToken === null) {
        const data = await request('/api/csrf', { signal });
        if (!data || typeof data.csrfToken !== 'string' || !/^[a-f0-9]{64}$/.test(data.csrfToken)) {
          throw new HttpError(500, 'Unable to establish a secure session.');
        }
        csrfToken = data.csrfToken;
      }
      headers['X-CSRF-Token'] = csrfToken;
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetchImpl(url.href, {
      method: verb,
      headers,
      credentials: 'same-origin',
      redirect: 'error',
      cache: 'no-store',
      signal,
      ...(unsafe && body !== undefined ? { body: JSON.stringify(body) } : {}),
    });
    if (response.status === 204) return null;

    let payload = null;
    const contentType = response.headers.get('content-type') || '';
    if (contentType.toLowerCase().includes('application/json')) {
      try { payload = await response.json(); } catch { /* Treat malformed JSON as a server failure. */ }
    }

    if (!response.ok) {
      if (response.status === 403 || response.status === 419) csrfToken = null;
      const requestId = response.headers.get('x-request-id');
      const retryValue = response.headers.get('retry-after');
      const retryAfter = retryValue && /^\d+$/.test(retryValue) ? Number(retryValue) : null;
      const safeMessage = response.status >= 500 ? 'The server could not complete this request.'
        : typeof payload?.error?.message === 'string' ? payload.error.message : 'Request denied.';
      throw new HttpError(response.status, safeMessage, {
        code: typeof payload?.error?.code === 'string' ? payload.error.code : 'request_failed',
        requestId, retryAfter,
      });
    }
    if (payload === null || typeof payload !== 'object') {
      throw new HttpError(502, 'Unexpected response from the server.');
    }
    return payload;
  }

  return {
    request,
    getSession(options = {}) { return request('/api/session', options); },
    async getCsrf(options = {}) {
      const data = await request('/api/csrf', options);
      if (typeof data?.csrfToken !== 'string' || !/^[a-f0-9]{64}$/.test(data.csrfToken)) {
        throw new HttpError(502, 'Invalid CSRF response.');
      }
      csrfToken = data.csrfToken;
      return csrfToken;
    },
    setCsrfToken(token) {
      if (typeof token !== 'string' || !/^[a-f0-9]{64}$/.test(token)) {
        throw new TypeError('Invalid session CSRF token.');
      }
      csrfToken = token;
    },
    clearSession() { csrfToken = null; },
  };
}
