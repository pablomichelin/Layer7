const test = require('node:test');
const assert = require('node:assert/strict');
const { buildAdminAuthResponse } = require('./auth-response');

function createSessionFixture() {
  return {
    token: 'opaque-session-token',
    metadata: {
      admin: {
        id: 7,
        email: 'pablo@systemup.inf.br',
        name: 'Pablo',
      },
      session: {
        id: 11,
        created_at: '2026-04-13T12:00:00.000Z',
        expires_at: '2026-04-13T12:30:00.000Z',
      },
    },
  };
}

test('buildAdminAuthResponse returns the stateful auth payload without bridge token', () => {
  const payload = buildAdminAuthResponse(createSessionFixture(), () => null);

  assert.deepEqual(payload, {
    admin: {
      id: 7,
      email: 'pablo@systemup.inf.br',
      name: 'Pablo',
    },
    session: {
      id: 11,
      created_at: '2026-04-13T12:00:00.000Z',
      expires_at: '2026-04-13T12:30:00.000Z',
    },
  });
});

test('buildAdminAuthResponse appends bearer bridge data only when available', () => {
  const payload = buildAdminAuthResponse(
    createSessionFixture(),
    (session) => `signed:${session.token}`
  );

  assert.deepEqual(payload, {
    admin: {
      id: 7,
      email: 'pablo@systemup.inf.br',
      name: 'Pablo',
    },
    session: {
      id: 11,
      created_at: '2026-04-13T12:00:00.000Z',
      expires_at: '2026-04-13T12:30:00.000Z',
    },
    token: 'signed:opaque-session-token',
    token_type: 'Bearer',
  });
});
