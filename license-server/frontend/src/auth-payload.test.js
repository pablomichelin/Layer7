import test from 'node:test';
import assert from 'node:assert/strict';
import { AUTH_SESSION_INVALID_MESSAGE } from './auth-messages.js';
import {
  assertAuthenticatedSessionPayload,
  isAuthenticatedSessionPayload,
  normalizeAuthenticatedSessionPayload,
} from './auth-payload.js';

test('isAuthenticatedSessionPayload requires both admin and session', () => {
  assert.equal(isAuthenticatedSessionPayload(null), false);
  assert.equal(isAuthenticatedSessionPayload({}), false);
  assert.equal(isAuthenticatedSessionPayload({ admin: { id: 7 } }), false);
  assert.equal(isAuthenticatedSessionPayload({ session: { id: 11 } }), false);
  assert.equal(
    isAuthenticatedSessionPayload({
      admin: { id: 7 },
      session: { id: 11 },
    }),
    true
  );
});

test('normalizeAuthenticatedSessionPayload clears malformed auth payloads', () => {
  assert.deepEqual(
    normalizeAuthenticatedSessionPayload({ token: 'bearer-token', admin: { id: 7 } }),
    {
      token: null,
      admin: null,
      session: null,
    }
  );
});

test('normalizeAuthenticatedSessionPayload preserves coherent auth payloads', () => {
  assert.deepEqual(
    normalizeAuthenticatedSessionPayload({
      token: 'bearer-token',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11 },
    }),
    {
      token: 'bearer-token',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11 },
    }
  );
});

test('assertAuthenticatedSessionPayload rejects malformed auth payloads', () => {
  assert.throws(
    () => assertAuthenticatedSessionPayload({
      token: 'bearer-token',
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
    }),
    new RegExp(AUTH_SESSION_INVALID_MESSAGE.replace('.', '\\.'))
  );
});

test('assertAuthenticatedSessionPayload preserves coherent auth payloads', () => {
  const data = {
    token: 'bearer-token',
    admin: { id: 7, email: 'pablo@systemup.inf.br' },
    session: { id: 11 },
  };

  assert.equal(assertAuthenticatedSessionPayload(data), data);
});
