import test from 'node:test';
import assert from 'node:assert/strict';
import { AUTH_SESSION_INVALID_MESSAGE } from './auth-messages.js';
import {
  bootstrapAuthSession,
  clearAuthSessionState,
  loginWithPassword,
  logoutAuthSession,
  refreshAuthSession,
} from './auth-controller.js';

function installWindowMock() {
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
}

function cleanupWindowMock() {
  delete global.window;
  delete global.Event;
}

test('bootstrapAuthSession hydrates state and clears loading on success', async () => {
  installWindowMock();
  let admin = null;
  let session = null;
  let loading = true;

  const data = await bootstrapAuthSession({
    getSession: async () => ({
      token: 'bearer-token',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11 },
    }),
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
    setLoading(value) {
      loading = value;
    },
    isActive() {
      return true;
    },
  });

  assert.deepEqual(data, {
    token: 'bearer-token',
    admin: { id: 7, email: 'pablo@systemup.inf.br' },
    session: { id: 11 },
  });
  assert.deepEqual(admin, { id: 7, email: 'pablo@systemup.inf.br' });
  assert.deepEqual(session, { id: 11 });
  assert.equal(loading, false);
  cleanupWindowMock();
});

test('bootstrapAuthSession clears auth state on failure', async () => {
  installWindowMock();
  let admin = { id: 1 };
  let session = { id: 2 };
  let loading = true;

  const data = await bootstrapAuthSession({
    getSession: async () => {
      throw new Error('not authenticated');
    },
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
    setLoading(value) {
      loading = value;
    },
    isActive() {
      return true;
    },
  });

  assert.equal(data, null);
  assert.equal(admin, null);
  assert.equal(session, null);
  assert.equal(loading, false);
  cleanupWindowMock();
});

test('bootstrapAuthSession clears malformed auth payloads returned by the backend', async () => {
  installWindowMock();
  let admin = { id: 1 };
  let session = { id: 2 };
  let loading = true;

  const data = await bootstrapAuthSession({
    getSession: async () => ({
      token: 'bearer-token',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
    }),
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
    setLoading(value) {
      loading = value;
    },
    isActive() {
      return true;
    },
  });

  assert.deepEqual(data, {
    token: 'bearer-token',
    admin: { id: 7, email: 'pablo@systemup.inf.br' },
  });
  assert.equal(admin, null);
  assert.equal(session, null);
  assert.equal(loading, false);
  cleanupWindowMock();
});

test('bootstrapAuthSession ignores results when the view is inactive', async () => {
  installWindowMock();
  let admin = 'unchanged';
  let session = 'unchanged';
  let loading = true;

  const data = await bootstrapAuthSession({
    getSession: async () => ({
      token: 'bearer-token',
      admin: { id: 7 },
      session: { id: 11 },
    }),
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
    setLoading(value) {
      loading = value;
    },
    isActive() {
      return false;
    },
  });

  assert.equal(data, null);
  assert.equal(admin, 'unchanged');
  assert.equal(session, 'unchanged');
  assert.equal(loading, true);
  cleanupWindowMock();
});

test('loginWithPassword applies authenticated session state', async () => {
  installWindowMock();
  let admin = null;
  let session = null;

  const data = await loginWithPassword({
    postSession: async (path, body, options) => {
      assert.equal(path, '/auth/login');
      assert.deepEqual(body, { email: 'pablo@systemup.inf.br', password: 'secret' });
      assert.deepEqual(options, { skipAuthRedirect: true });
      return {
        token: 'bearer-token',
        admin: { id: 7, email: 'pablo@systemup.inf.br' },
        session: { id: 11 },
      };
    },
    email: 'pablo@systemup.inf.br',
    password: 'secret',
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
  });

  assert.deepEqual(data.admin, { id: 7, email: 'pablo@systemup.inf.br' });
  assert.deepEqual(admin, { id: 7, email: 'pablo@systemup.inf.br' });
  assert.deepEqual(session, { id: 11 });
  cleanupWindowMock();
});

test('loginWithPassword rejects malformed auth payloads returned by the backend', async () => {
  installWindowMock();
  let admin = { id: 1 };
  let session = { id: 2 };

  await assert.rejects(
    loginWithPassword({
      postSession: async () => ({
        token: 'bearer-token',
        admin: { id: 7, email: 'pablo@systemup.inf.br' },
      }),
      email: 'pablo@systemup.inf.br',
      password: 'secret',
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }),
    new RegExp(AUTH_SESSION_INVALID_MESSAGE.replace('.', '\\.'))
  );

  assert.equal(admin, null);
  assert.equal(session, null);
  cleanupWindowMock();
});

test('refreshAuthSession reapplies authenticated session state', async () => {
  installWindowMock();
  let admin = null;
  let session = null;

  const data = await refreshAuthSession({
    getSession: async (path, options) => {
      assert.equal(path, '/auth/session');
      assert.deepEqual(options, { skipAuthRedirect: true });
      return {
        token: 'bearer-token',
        admin: { id: 7, email: 'pablo@systemup.inf.br' },
        session: { id: 11 },
      };
    },
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
  });

  assert.deepEqual(data.session, { id: 11 });
  assert.deepEqual(admin, { id: 7, email: 'pablo@systemup.inf.br' });
  assert.deepEqual(session, { id: 11 });
  cleanupWindowMock();
});

test('refreshAuthSession rejects malformed auth payloads returned by the backend', async () => {
  installWindowMock();
  let admin = { id: 1 };
  let session = { id: 2 };

  await assert.rejects(
    refreshAuthSession({
      getSession: async () => ({
        token: 'bearer-token',
        admin: { id: 7, email: 'pablo@systemup.inf.br' },
      }),
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }),
    new RegExp(AUTH_SESSION_INVALID_MESSAGE.replace('.', '\\.'))
  );

  assert.equal(admin, null);
  assert.equal(session, null);
  cleanupWindowMock();
});

test('logoutAuthSession returns the backend payload and clears auth state on success', async () => {
  installWindowMock();
  let admin = { id: 7 };
  let session = { id: 11 };

  const data = await logoutAuthSession({
    postSession: async (path, body, options) => {
      assert.equal(path, '/auth/logout');
      assert.equal(body, undefined);
      assert.deepEqual(options, { skipAuthRedirect: true });
      return { message: 'Sessao encerrada' };
    },
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
  });

  assert.deepEqual(data, { message: 'Sessao encerrada' });
  assert.equal(admin, null);
  assert.equal(session, null);
  cleanupWindowMock();
});

test('logoutAuthSession clears auth state even if the backend call fails', async () => {
  installWindowMock();
  let admin = { id: 7 };
  let session = { id: 11 };

  await assert.rejects(
    logoutAuthSession({
      postSession: async () => {
        throw new Error('network error');
      },
      setAdmin(value) {
        admin = value;
      },
      setSession(value) {
        session = value;
      },
    }),
    /network error/
  );

  assert.equal(admin, null);
  assert.equal(session, null);
  cleanupWindowMock();
});

test('clearAuthSessionState clears the in-memory auth state', () => {
  installWindowMock();
  let admin = { id: 7 };
  let session = { id: 11 };

  clearAuthSessionState({
    setAdmin(value) {
      admin = value;
    },
    setSession(value) {
      session = value;
    },
  });

  assert.equal(admin, null);
  assert.equal(session, null);
  cleanupWindowMock();
});
