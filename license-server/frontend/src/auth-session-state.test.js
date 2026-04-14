import test from 'node:test';
import assert from 'node:assert/strict';
import { AUTH_SESSION_INVALID_MESSAGE } from './auth-messages.js';
import {
  applyAuthenticatedSession,
  clearAuthenticatedSession,
  syncAuthenticatedSession,
} from './auth-session-state.js';
import { api } from './api.js';

test('applyAuthenticatedSession persists the token and updates auth state', () => {
  let admin = 'stale-admin';
  let session = 'stale-session';

  applyAuthenticatedSession(
    {
      token: 'bearer-token-123',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11, expires_at: '2026-04-13T12:00:00.000Z' },
    },
    {
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }
  );

  assert.deepEqual(admin, { id: 7, email: 'pablo@systemup.inf.br' });
  assert.deepEqual(session, { id: 11, expires_at: '2026-04-13T12:00:00.000Z' });
});

test('applyAuthenticatedSession clears malformed auth payloads instead of keeping partial state', () => {
  let admin = 'stale-admin';
  let session = 'stale-session';

  applyAuthenticatedSession(
    {
      token: 'bearer-token-123',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
    },
    {
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }
  );

  assert.equal(admin, null);
  assert.equal(session, null);
});

test('syncAuthenticatedSession returns coherent auth payloads after applying state', () => {
  let admin = null;
  let session = null;
  const data = {
    token: 'bearer-token-123',
    admin: { id: 7, email: 'pablo@systemup.inf.br' },
    session: { id: 11, expires_at: '2026-04-13T12:00:00.000Z' },
  };

  assert.equal(
    syncAuthenticatedSession(data, {
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }),
    data
  );

  assert.deepEqual(admin, data.admin);
  assert.deepEqual(session, data.session);
});

test('syncAuthenticatedSession throws after clearing malformed auth state', () => {
  let admin = 'stale-admin';
  let session = 'stale-session';

  assert.throws(
    () => syncAuthenticatedSession(
      {
        token: 'bearer-token-123',
        admin: { id: 7, email: 'pablo@systemup.inf.br' },
      },
      {
        setAdmin(value) {
          admin = value;
        },
        setSession(value) {
          session = value;
        },
      }
    ),
    new RegExp(AUTH_SESSION_INVALID_MESSAGE.replace('.', '\\.'))
  );

  assert.equal(admin, null);
  assert.equal(session, null);
});

test('clearAuthenticatedSession removes the token and clears auth state', () => {
  let admin = { id: 7 };
  let session = { id: 11 };

  clearAuthenticatedSession({
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
  });

  assert.equal(admin, null);
  assert.equal(session, null);
});

test('applyAuthenticatedSession keeps the bearer token only in memory', async () => {
  global.window = {
    location: { href: '/dashboard' },
    dispatchEvent() {
      return true;
    },
  };
  global.Event = class Event {
    constructor(type) {
      this.type = type;
    }
  };

  let fetchCall = null;
  global.fetch = async (url, options) => {
    fetchCall = { url, options };
    return {
      status: 200,
      ok: true,
      headers: {
        get() {
          return 'application/json';
        },
      },
      async json() {
        return { ok: true };
      },
      async text() {
        return JSON.stringify({ ok: true });
      },
    };
  };

  applyAuthenticatedSession(
    {
      token: 'bearer-token-123',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11, expires_at: '2026-04-13T12:00:00.000Z' },
    },
    {
      setAdmin() {},
      setSession() {},
    }
  );

  await api('/dashboard');

  assert.equal(fetchCall.options.headers.Authorization, 'Bearer bearer-token-123');

  delete global.fetch;
  delete global.window;
  delete global.Event;
});

test('applyAuthenticatedSession does not persist the bearer token for malformed auth payloads', async () => {
  global.window = {
    location: { href: '/dashboard' },
    dispatchEvent() {
      return true;
    },
  };
  global.Event = class Event {
    constructor(type) {
      this.type = type;
    }
  };

  let fetchCall = null;
  global.fetch = async (url, options) => {
    fetchCall = { url, options };
    return {
      status: 200,
      ok: true,
      headers: {
        get() {
          return 'application/json';
        },
      },
      async json() {
        return { ok: true };
      },
      async text() {
        return JSON.stringify({ ok: true });
      },
    };
  };

  applyAuthenticatedSession(
    {
      token: 'bearer-token-123',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
    },
    {
      setAdmin() {},
      setSession() {},
    }
  );

  await api('/dashboard');

  assert.equal(fetchCall.options.headers.Authorization, undefined);

  delete global.fetch;
  delete global.window;
  delete global.Event;
});
