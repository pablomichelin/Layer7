import test from 'node:test';
import assert from 'node:assert/strict';
import { AUTH_INVALID_EVENT } from './auth-events.js';
import { AUTH_SESSION_EXPIRED_MESSAGE } from './auth-messages.js';
import { ADMIN_LOGIN_ROUTE } from './panel-routes.js';
import { api, clearStoredAuthToken, persistAuthToken } from './api.js';

function installWindowMock() {
  const listeners = new Map();
  const events = [];
  const windowMock = {
    location: { href: '/dashboard' },
    dispatchEvent(event) {
      events.push(event.type);
      const handlers = listeners.get(event.type) || [];
      handlers.forEach((handler) => handler(event));
      return true;
    },
    addEventListener(type, handler) {
      const handlers = listeners.get(type) || [];
      handlers.push(handler);
      listeners.set(type, handlers);
    },
    removeEventListener(type, handler) {
      const handlers = listeners.get(type) || [];
      listeners.set(
        type,
        handlers.filter((candidate) => candidate !== handler)
      );
    },
  };

  global.window = windowMock;
  global.Event = class Event {
    constructor(type) {
      this.type = type;
    }
  };

  return { windowMock, events };
}

function createJsonResponse(status, body) {
  return {
    status,
    ok: status >= 200 && status < 300,
    headers: {
      get(name) {
        if (name.toLowerCase() === 'content-type') {
          return 'application/json';
        }
        return null;
      },
    },
    async json() {
      return body;
    },
    async text() {
      return JSON.stringify(body);
    },
  };
}

test('api envia Authorization Bearer a partir do token persistido', async () => {
  installWindowMock();
  persistAuthToken('token-abc');

  let fetchCall = null;
  global.fetch = async (url, options) => {
    fetchCall = { url, options };
    return createJsonResponse(200, { ok: true });
  };

  const result = await api('/dashboard');

  assert.deepEqual(result, { ok: true });
  assert.equal(fetchCall.url, '/api/dashboard');
  assert.equal(fetchCall.options.method, undefined);
  assert.equal(fetchCall.options.credentials, 'same-origin');
  assert.equal(fetchCall.options.headers.Authorization, 'Bearer token-abc');

  delete global.fetch;
  delete global.window;
  delete global.Event;
});

test('api respeita authorization/content-type informados em lowercase', async () => {
  installWindowMock();
  persistAuthToken('token-abc');

  let fetchCall = null;
  global.fetch = async (url, options) => {
    fetchCall = { url, options };
    return createJsonResponse(200, { ok: true });
  };

  const result = await api('/dashboard', {
    method: 'POST',
    body: JSON.stringify({ ok: true }),
    headers: {
      authorization: 'Bearer custom-token',
      'content-type': 'application/custom+json',
    },
  });

  assert.deepEqual(result, { ok: true });
  assert.equal(fetchCall.url, '/api/dashboard');
  assert.equal(fetchCall.options.headers.authorization, 'Bearer custom-token');
  assert.equal(fetchCall.options.headers.Authorization, undefined);
  assert.equal(fetchCall.options.headers['content-type'], 'application/custom+json');
  assert.equal(fetchCall.options.headers['Content-Type'], undefined);

  delete global.fetch;
  delete global.window;
  delete global.Event;
});

test('api nao injecta bearer herdado em /auth/login', async () => {
  installWindowMock();
  persistAuthToken('token-abc');

  let fetchCall = null;
  global.fetch = async (url, options) => {
    fetchCall = { url, options };
    return createJsonResponse(200, { ok: true });
  };

  const result = await api('/auth/login', {
    method: 'POST',
    body: JSON.stringify({ email: 'pablo@systemup.inf.br', password: 'secret' }),
  });

  assert.deepEqual(result, { ok: true });
  assert.equal(fetchCall.url, '/api/auth/login');
  assert.equal(fetchCall.options.headers.Authorization, undefined);

  clearStoredAuthToken();
  delete global.fetch;
  delete global.window;
  delete global.Event;
});

test('api limpa token, emite evento e redirecciona em 401 autenticado', async () => {
  const { events } = installWindowMock();
  persistAuthToken('token-abc');

  global.fetch = async () => createJsonResponse(401, { error: 'Autenticacao necessaria.' });

  await assert.rejects(api('/dashboard'), new RegExp(AUTH_SESSION_EXPIRED_MESSAGE));

  global.fetch = async (url, options) => {
    return createJsonResponse(200, { authorization: options.headers.Authorization || null });
  };
  const retry = await api('/dashboard', { skipAuthRedirect: true });
  assert.equal(retry.authorization, null);
  assert.deepEqual(events, [AUTH_INVALID_EVENT]);
  assert.equal(window.location.href, ADMIN_LOGIN_ROUTE);

  delete global.fetch;
  delete global.window;
  delete global.Event;
});

test('api preserva token quando skipAuthRedirect=true', async () => {
  installWindowMock();
  persistAuthToken('token-abc');

  global.fetch = async () => createJsonResponse(401, { error: 'Autenticacao necessaria.' });

  await assert.rejects(
    api('/auth/session', { skipAuthRedirect: true }),
    /Autenticacao necessaria/
  );

  global.fetch = async (url, options) => {
    return createJsonResponse(200, { authorization: options.headers.Authorization || null });
  };
  const retry = await api('/dashboard', { skipAuthRedirect: true });
  assert.equal(retry.authorization, 'Bearer token-abc');

  clearStoredAuthToken();
  delete global.fetch;
  delete global.window;
  delete global.Event;
});
