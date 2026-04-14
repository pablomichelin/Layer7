const test = require('node:test');
const assert = require('node:assert/strict');
const {
  buildAdminAccessDeniedAuditPayload,
  buildLoginErrorAuditPayload,
  buildLoginFailedAuditPayload,
  buildLoginSucceededAuditPayload,
  buildLockedScopesMetadata,
  buildLoginFailureAuditMetadata,
  buildLoginLockedAuditPayload,
  buildLoginRejectedAuditPayload,
  buildLogoutErrorAuditPayload,
  buildLogoutAuditPayload,
  buildSessionValidationErrorAuditPayload,
  buildSessionCreatedAuditPayload,
} = require('./auth-route-helpers');

test('buildAdminAccessDeniedAuditPayload centralizes denied session access data', () => {
  const req = { path: '/api/dashboard' };

  assert.deepEqual(
    buildAdminAccessDeniedAuditPayload({ req }),
    {
      component: 'auth',
      eventType: 'admin_access_denied',
      req,
      result: 'denied',
      reason: 'invalid_or_expired_session',
    }
  );
});

test('buildLockedScopesMetadata keeps only locked scopes', () => {
  const metadata = buildLockedScopesMetadata([
    {
      scopeType: 'ip',
      lockedUntil: new Date('2026-04-13T12:30:00.000Z'),
    },
    {
      scopeType: 'identity',
      lockedUntil: null,
    },
    {
      scopeType: 'email_ip',
      lockedUntil: new Date('2026-04-13T13:00:00.000Z'),
    },
  ]);

  assert.deepEqual(metadata, [
    {
      scope_type: 'ip',
      locked_until: '2026-04-13T12:30:00.000Z',
    },
    {
      scope_type: 'email_ip',
      locked_until: '2026-04-13T13:00:00.000Z',
    },
  ]);
});

test('buildLoginFailureAuditMetadata includes lockout scopes and optional admin id', () => {
  const metadata = buildLoginFailureAuditMetadata({
    adminId: 7,
    guards: [
      {
        scopeType: 'ip',
        lockedUntil: new Date('2026-04-13T12:30:00.000Z'),
      },
    ],
  });

  assert.deepEqual(metadata, {
    admin_id: 7,
    lockout_scopes: [
      {
        scope_type: 'ip',
        locked_until: '2026-04-13T12:30:00.000Z',
      },
    ],
  });
});

test('buildLoginFailureAuditMetadata omits admin id when not provided', () => {
  const metadata = buildLoginFailureAuditMetadata({
    guards: [],
  });

  assert.deepEqual(metadata, {
    lockout_scopes: [],
  });
});

test('buildLoginRejectedAuditPayload builds the denied login payload', () => {
  const req = { path: '/api/auth/login' };

  assert.deepEqual(
    buildLoginRejectedAuditPayload({
      email: 'admin@example.com',
      req,
      reason: 'missing_credentials',
    }),
    {
      component: 'auth',
      eventType: 'login_rejected',
      actorIdentifier: 'admin@example.com',
      req,
      result: 'denied',
      reason: 'missing_credentials',
    }
  );
});

test('buildLoginLockedAuditPayload includes the active lock metadata', () => {
  const req = { path: '/api/auth/login' };

  assert.deepEqual(
    buildLoginLockedAuditPayload({
      email: 'admin@example.com',
      req,
      activeLock: {
        scopeType: 'identity',
        lockedUntil: new Date('2026-04-13T12:30:00.000Z'),
      },
    }),
    {
      component: 'auth',
      eventType: 'login_locked',
      actorIdentifier: 'admin@example.com',
      req,
      result: 'blocked',
      reason: 'identity_lockout_active',
      metadata: {
        locked_until: '2026-04-13T12:30:00.000Z',
      },
    }
  );
});

test('buildLoginFailedAuditPayload centralizes invalid credential audit data', () => {
  const req = { path: '/api/auth/login' };

  assert.deepEqual(
    buildLoginFailedAuditPayload({
      email: 'admin@example.com',
      req,
      adminId: 9,
      guards: [
        {
          scopeType: 'ip',
          lockedUntil: new Date('2026-04-13T12:30:00.000Z'),
        },
      ],
    }),
    {
      component: 'auth',
      eventType: 'login_failed',
      actorIdentifier: 'admin@example.com',
      req,
      result: 'denied',
      reason: 'invalid_credentials',
      metadata: {
        admin_id: 9,
        lockout_scopes: [
          {
            scope_type: 'ip',
            locked_until: '2026-04-13T12:30:00.000Z',
          },
        ],
      },
    }
  );
});

test('buildLoginErrorAuditPayload centralizes login exception audit data', () => {
  const req = { path: '/api/auth/login' };

  assert.deepEqual(
    buildLoginErrorAuditPayload({
      email: 'admin@example.com',
      req,
    }),
    {
      component: 'auth',
      eventType: 'login_error',
      actorIdentifier: 'admin@example.com',
      req,
      result: 'error',
      reason: 'login_exception',
    }
  );
});

test('buildLoginSucceededAuditPayload centralizes successful login audit data', () => {
  const req = { path: '/api/auth/login' };
  const admin = { id: 7, email: 'admin@example.com' };

  assert.deepEqual(
    buildLoginSucceededAuditPayload({ admin, req }),
    {
      component: 'auth',
      eventType: 'login_succeeded',
      adminId: 7,
      actorIdentifier: 'admin@example.com',
      req,
      result: 'success',
      reason: 'credentials_validated',
    }
  );
});

test('buildSessionCreatedAuditPayload centralizes issued session audit data', () => {
  const req = { path: '/api/auth/login' };
  const admin = { id: 7, email: 'admin@example.com' };
  const session = {
    metadata: {
      session: { id: 21 },
    },
  };

  assert.deepEqual(
    buildSessionCreatedAuditPayload({ admin, req, session }),
    {
      component: 'auth',
      eventType: 'session_created',
      adminId: 7,
      actorIdentifier: 'admin@example.com',
      req,
      result: 'success',
      reason: 'admin_session_issued',
      metadata: { session_id: 21 },
    }
  );
});

test('buildLogoutAuditPayload distinguishes normal logout from logout without session', () => {
  const req = { path: '/api/auth/logout' };
  const session = {
    metadata: {
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11 },
    },
  };

  assert.deepEqual(
    buildLogoutAuditPayload({ req, session, token: 'session-token' }),
    {
      component: 'auth',
      eventType: 'session_revoked',
      adminId: 7,
      actorIdentifier: 'pablo@systemup.inf.br',
      req,
      result: 'success',
      reason: 'logout',
      metadata: { session_id: 11 },
    }
  );

  assert.deepEqual(
    buildLogoutAuditPayload({ req, session: null, token: null }),
    {
      component: 'auth',
      eventType: 'session_revoked',
      adminId: null,
      actorIdentifier: null,
      req,
      result: 'success',
      reason: 'logout_without_session',
      metadata: null,
    }
  );
});

test('buildLogoutErrorAuditPayload centralizes logout exception audit data', () => {
  const req = { path: '/api/auth/logout' };

  assert.deepEqual(
    buildLogoutErrorAuditPayload({ req }),
    {
      component: 'auth',
      eventType: 'logout_error',
      req,
      result: 'error',
      reason: 'logout_exception',
    }
  );
});

test('buildSessionValidationErrorAuditPayload centralizes session validation exception data', () => {
  const req = { path: '/api/dashboard' };

  assert.deepEqual(
    buildSessionValidationErrorAuditPayload({ req }),
    {
      component: 'auth',
      eventType: 'session_validation_error',
      req,
      result: 'error',
      reason: 'session_validation_exception',
    }
  );
});
