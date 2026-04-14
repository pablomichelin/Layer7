const test = require('node:test');
const assert = require('node:assert/strict');
const { createAuthMiddleware } = require('./auth-middleware');

function createResponseDouble() {
  return {
    statusCode: null,
    body: null,
    status(code) {
      this.statusCode = code;
      return this;
    },
    json(payload) {
      this.body = payload;
      return this;
    },
  };
}

test('auth middleware populates request context for a valid session', async () => {
  const req = {};
  const res = createResponseDouble();
  const nextCalls = [];
  const session = {
    token: 'session-token',
    metadata: {
      admin: { id: 7, email: 'pablo@systemup.inf.br' },
      session: { id: 11 },
    },
  };
  const middleware = createAuthMiddleware({
    resolveSession: async () => session,
    clearSessionCookie() {
      throw new Error('clearSessionCookie should not be called');
    },
    auditAdminEvent: async () => {
      throw new Error('auditAdminEvent should not be called');
    },
    authRequiredMessage: 'Autenticacao necessaria.',
    internalErrorMessage: 'Erro interno.',
  });

  await middleware(req, res, () => {
    nextCalls.push('next');
  });

  assert.deepEqual(req.admin, session.metadata.admin);
  assert.deepEqual(req.adminSession, session.metadata.session);
  assert.equal(req.adminToken, session.token);
  assert.deepEqual(nextCalls, ['next']);
  assert.equal(res.statusCode, null);
  assert.equal(res.body, null);
});

test('auth middleware clears the cookie and returns 401 for an invalid session', async () => {
  const req = { path: '/api/dashboard' };
  const res = createResponseDouble();
  const cookieClears = [];
  const auditCalls = [];
  const middleware = createAuthMiddleware({
    resolveSession: async () => null,
    clearSessionCookie(response) {
      cookieClears.push(response);
    },
    auditAdminEvent: async (payload) => {
      auditCalls.push(payload);
    },
    authRequiredMessage: 'Autenticacao necessaria.',
    internalErrorMessage: 'Erro interno.',
  });

  await middleware(req, res, () => {
    throw new Error('next should not be called');
  });

  assert.equal(cookieClears.length, 1);
  assert.equal(res.statusCode, 401);
  assert.deepEqual(res.body, { error: 'Autenticacao necessaria.' });
  assert.equal(auditCalls.length, 1);
  assert.equal(auditCalls[0].eventType, 'admin_access_denied');
  assert.equal(auditCalls[0].reason, 'invalid_or_expired_session');
});

test('auth middleware clears the cookie, audits and returns 500 on resolver error', async () => {
  const req = { path: '/api/dashboard' };
  const res = createResponseDouble();
  const cookieClears = [];
  const auditCalls = [];
  const logLines = [];
  const middleware = createAuthMiddleware({
    resolveSession: async () => {
      throw new Error('db unavailable');
    },
    clearSessionCookie(response) {
      cookieClears.push(response);
    },
    auditAdminEvent: async (payload) => {
      auditCalls.push(payload);
    },
    logError: (...args) => {
      logLines.push(args.join(' '));
    },
    authRequiredMessage: 'Autenticacao necessaria.',
    internalErrorMessage: 'Erro interno.',
  });

  await middleware(req, res, () => {
    throw new Error('next should not be called');
  });

  assert.equal(cookieClears.length, 1);
  assert.equal(res.statusCode, 500);
  assert.deepEqual(res.body, { error: 'Erro interno.' });
  assert.equal(auditCalls.length, 1);
  assert.equal(auditCalls[0].eventType, 'session_validation_error');
  assert.equal(auditCalls[0].reason, 'session_validation_exception');
  assert.equal(logLines.length, 1);
  assert.match(logLines[0], /db unavailable/);
});
