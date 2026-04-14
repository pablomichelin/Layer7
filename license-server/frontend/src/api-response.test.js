import test from 'node:test';
import assert from 'node:assert/strict';
import {
  parseApiError,
  parseApiSuccess,
  shouldHandleInvalidSession,
} from './api-response.js';

function createJsonResponse(status, body) {
  return {
    status,
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

test('shouldHandleInvalidSession matches only protected non-auth 401 responses', () => {
  assert.equal(
    shouldHandleInvalidSession({
      path: '/dashboard',
      status: 401,
      skipAuthRedirect: false,
    }),
    true
  );

  assert.equal(
    shouldHandleInvalidSession({
      path: '/auth/session',
      status: 401,
      skipAuthRedirect: false,
    }),
    false
  );

  assert.equal(
    shouldHandleInvalidSession({
      path: '/dashboard',
      status: 401,
      skipAuthRedirect: true,
    }),
    false
  );

  assert.equal(
    shouldHandleInvalidSession({
      path: '/dashboard',
      status: 500,
      skipAuthRedirect: false,
    }),
    false
  );
});

test('parseApiError prefers backend error messages and falls back to status code', async () => {
  assert.equal(
    await parseApiError(createJsonResponse(401, { error: 'Autenticacao necessaria.' })),
    'Autenticacao necessaria.'
  );

  assert.equal(
    await parseApiError({
      status: 500,
      async json() {
        throw new Error('invalid json');
      },
    }),
    'Erro 500'
  );
});

test('parseApiSuccess handles 204, json and plain text responses', async () => {
  assert.equal(
    await parseApiSuccess({
      status: 204,
      headers: { get() { return null; } },
      async json() {
        throw new Error('should not parse json');
      },
      async text() {
        throw new Error('should not parse text');
      },
    }),
    null
  );

  assert.deepEqual(
    await parseApiSuccess(createJsonResponse(200, { ok: true })),
    { ok: true }
  );

  assert.equal(
    await parseApiSuccess({
      status: 200,
      headers: {
        get(name) {
          if (name.toLowerCase() === 'content-type') {
            return 'text/plain';
          }
          return null;
        },
      },
      async json() {
        throw new Error('should not parse json');
      },
      async text() {
        return 'plain text';
      },
    }),
    'plain text'
  );
});
