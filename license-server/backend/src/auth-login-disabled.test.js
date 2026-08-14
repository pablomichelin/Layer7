const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const bcrypt = require('bcryptjs');
const {
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
  ADMIN_AUTH_LOCKED_MESSAGE,
} = require('./admin-surface');

const REAL_PASSWORD = 'correct-password-12';
const REAL_HASH = bcrypt.hashSync(REAL_PASSWORD, 4);
const WRONG_PASSWORD = 'wrong-password-12';

function passthrough(_req, _res, next) {
  next();
}

function createResponse() {
  return {
    statusCode: 200,
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

function loginRequest({ email, password }) {
  return {
    body: { email, password },
    ip: '203.0.113.88',
    headers: { 'user-agent': 'p3-3a-test' },
    originalUrl: '/api/auth/login',
    path: '/api/auth/login',
  };
}

function adminRow(overrides = {}) {
  return {
    id: 9,
    email: 'disabled@example.com',
    password_hash: REAL_HASH,
    is_active: false,
    totp_enabled: false,
    totp_secret: null,
    name: 'Disabled',
    is_owner: false,
    permissions: [],
    ...overrides,
  };
}

function loadLoginRouter({ rows = [], activeLock = null } = {}) {
  const dbPath = require.resolve('./db');
  const surfacePath = require.resolve('./admin-surface');
  const sessionPath = require.resolve('./session');
  const bcryptPath = require.resolve('bcryptjs');
  const authRoutePath = require.resolve('./routes/auth');
  const realSurface = require('./admin-surface');
  const realSession = require('./session');

  const compares = [];
  const failures = [];
  const audits = [];
  const sessions = [];
  const cookies = [];
  const resets = [];

  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: {
      async query(sql) {
        if (/SELECT \* FROM admins WHERE LOWER\(email\)/i.test(String(sql))) {
          return { rows };
        }
        throw new Error(`SQL inesperado no teste P3-3A: ${String(sql).slice(0, 160)}`);
      },
    },
  };

  require.cache[surfacePath] = {
    id: surfacePath,
    filename: surfacePath,
    loaded: true,
    exports: {
      ...realSurface,
      loginIpLimiter: passthrough,
      loginIdentityLimiter: passthrough,
      getActiveLoginLock: async () => activeLock,
      registerLoginFailure: async (args) => {
        failures.push({ email: args.email, req: args.req });
        return [
          { scopeType: 'ip', scopeKey: '203.0.113.88', failureCount: 1, lockedUntil: null },
          { scopeType: 'account', scopeKey: args.email, failureCount: 1, lockedUntil: null },
        ];
      },
      resetLoginProtection: async (args) => {
        resets.push(args.email);
      },
      auditAdminEvent: async (payload) => {
        audits.push(payload);
      },
    },
  };

  require.cache[sessionPath] = {
    id: sessionPath,
    filename: sessionPath,
    loaded: true,
    exports: {
      ...realSession,
      requireSecureSessionRequest: () => true,
      createSession: async (admin) => {
        const now = new Date();
        sessions.push(admin.id);
        return {
          token: 'session-token',
          metadata: {
            admin,
            session: {
              id: 77,
              created_at: now,
              last_seen_at: now,
              expires_at: new Date(now.getTime() + 30 * 60 * 1000),
              absolute_expires_at: new Date(now.getTime() + 8 * 60 * 60 * 1000),
            },
          },
        };
      },
      setSessionCookie: () => {
        cookies.push('set');
      },
      clearSessionCookie: () => {},
    },
  };

  require.cache[bcryptPath] = {
    id: bcryptPath,
    filename: bcryptPath,
    loaded: true,
    exports: {
      ...bcrypt,
      compare: async (password, hash) => {
        compares.push({ password, hash });
        return bcrypt.compare(password, hash);
      },
    },
  };

  delete require.cache[authRoutePath];
  const router = require('./routes/auth');

  return {
    router,
    compares,
    failures,
    audits,
    sessions,
    cookies,
    resets,
  };
}

async function postLogin(router, req) {
  const layer = router.stack.find((item) => (
    item.route && item.route.path === '/login' && item.route.methods.post
  ));
  assert.ok(layer, 'rota POST /login tem de existir');
  const handler = layer.route.stack[layer.route.stack.length - 1].handle;
  const res = createResponse();
  await handler(req, res, (err) => {
    if (err) {
      throw err;
    }
  });
  return res;
}

test('P3-3A — disabled e nonexistent devolvem o mesmo 401 generico e incrementam guardas', async () => {
  const unknown = loadLoginRouter({ rows: [] });
  const disabled = loadLoginRouter({
    rows: [adminRow({ email: 'disabled@example.com' })],
  });

  const unknownRes = await postLogin(
    unknown.router,
    loginRequest({ email: 'nobody@example.com', password: REAL_PASSWORD })
  );
  const disabledRes = await postLogin(
    disabled.router,
    loginRequest({ email: 'disabled@example.com', password: REAL_PASSWORD })
  );

  assert.equal(unknownRes.statusCode, 401);
  assert.equal(disabledRes.statusCode, 401);
  assert.deepEqual(unknownRes.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.deepEqual(disabledRes.body, unknownRes.body);
  assert.equal(unknown.failures.length, 1);
  assert.equal(disabled.failures.length, 1);
  assert.equal(unknown.failures[0].email, 'nobody@example.com');
  assert.equal(disabled.failures[0].email, 'disabled@example.com');
  assert.equal(unknown.sessions.length, 0);
  assert.equal(disabled.sessions.length, 0);
  assert.equal(unknown.cookies.length, 0);
  assert.equal(disabled.cookies.length, 0);
  assert.equal(unknown.resets.length, 0);
  assert.equal(disabled.resets.length, 0);
});

test('P3-3A — bcrypt usa hash dummy no unknown e hash real no disabled', async () => {
  const unknown = loadLoginRouter({ rows: [] });
  const disabled = loadLoginRouter({
    rows: [adminRow({ email: 'disabled@example.com' })],
  });

  await postLogin(
    unknown.router,
    loginRequest({ email: 'nobody@example.com', password: REAL_PASSWORD })
  );
  await postLogin(
    disabled.router,
    loginRequest({ email: 'disabled@example.com', password: WRONG_PASSWORD })
  );

  assert.equal(unknown.compares.length, 1);
  assert.equal(disabled.compares.length, 1);
  assert.match(unknown.compares[0].hash, /^\$2[aby]\$12\$/);
  assert.equal(disabled.compares[0].hash, REAL_HASH);
  assert.notEqual(unknown.compares[0].hash, REAL_HASH);
  assert.equal(unknown.router.DUMMY_PASSWORD_HASH, unknown.compares[0].hash);
});

test('P3-3A — disabled com password correcta nao cria sessao e nao vaza estado no body', async () => {
  const harness = loadLoginRouter({
    rows: [adminRow({ email: 'disabled@example.com' })],
  });
  const res = await postLogin(
    harness.router,
    loginRequest({ email: 'disabled@example.com', password: REAL_PASSWORD })
  );

  assert.equal(res.statusCode, 401);
  assert.deepEqual(res.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.compares.length, 1);
  assert.equal(harness.compares[0].hash, REAL_HASH);
  assert.equal(harness.failures.length, 1);
  assert.equal(harness.sessions.length, 0);
  assert.doesNotMatch(JSON.stringify(res.body), /desactiv/i);
  assert.equal(
    harness.audits.some((event) => event.reason === 'account_disabled'),
    true,
    'auditoria interna continua a distinguir conta desactivada'
  );
});

test('P3-3A — conta activa com password errada continua 401 + guarda', async () => {
  const harness = loadLoginRouter({
    rows: [adminRow({ email: 'active@example.com', is_active: true })],
  });
  const res = await postLogin(
    harness.router,
    loginRequest({ email: 'active@example.com', password: WRONG_PASSWORD })
  );

  assert.equal(res.statusCode, 401);
  assert.deepEqual(res.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.failures.length, 1);
  assert.equal(harness.sessions.length, 0);
  assert.equal(harness.compares[0].hash, REAL_HASH);
});

test('P3-3A — sucesso de conta activa sem TOTP permanece inalterado', async () => {
  const harness = loadLoginRouter({
    rows: [adminRow({ id: 3, email: 'owner@example.com', is_active: true })],
  });
  const res = await postLogin(
    harness.router,
    loginRequest({ email: 'owner@example.com', password: REAL_PASSWORD })
  );

  assert.equal(res.statusCode, 200);
  assert.equal(harness.sessions.length, 1);
  assert.equal(harness.sessions[0], 3);
  assert.equal(harness.cookies.length, 1);
  assert.equal(harness.failures.length, 0);
  assert.equal(harness.resets.length, 1);
});

test('P3-3A — lock activo continua 429 e TOTP required nao muda', async () => {
  const locked = loadLoginRouter({
    rows: [],
    activeLock: {
      scopeType: 'account',
      scopeKey: 'locked@example.com',
      lockedUntil: new Date(Date.now() + 15 * 60 * 1000),
    },
  });
  const lockedRes = await postLogin(
    locked.router,
    loginRequest({ email: 'locked@example.com', password: REAL_PASSWORD })
  );
  assert.equal(lockedRes.statusCode, 429);
  assert.deepEqual(lockedRes.body, { error: ADMIN_AUTH_LOCKED_MESSAGE });
  assert.equal(locked.compares.length, 0);
  assert.equal(locked.failures.length, 0);

  const totp = loadLoginRouter({
    rows: [adminRow({
      id: 4,
      email: 'totp@example.com',
      is_active: true,
      totp_enabled: true,
      totp_secret: 'MFRGGZDFMZTWQ2LK',
    })],
  });
  process.env.ADMIN_BEARER_JWT_SECRET = process.env.ADMIN_BEARER_JWT_SECRET || 'p3-3a-test-secret-32-bytes-minimum';
  const totpRes = await postLogin(
    totp.router,
    loginRequest({ email: 'totp@example.com', password: REAL_PASSWORD })
  );
  assert.equal(totpRes.body.status, 'totp_required');
  assert.equal(typeof totpRes.body.challenge_token, 'string');
  assert.equal(totp.sessions.length, 0);
  assert.equal(totp.resets.length, 0);
  assert.equal(totp.failures.length, 0);
});

test('P3-3A — CSRF e limites 5/15 e 10/15 ficam fora deste ficheiro', () => {
  const source = fs.readFileSync(path.join(__dirname, 'routes/auth.js'), 'utf8');
  assert.match(source, /DUMMY_PASSWORD_HASH/);
  assert.match(source, /registerLoginFailure/);
  assert.doesNotMatch(source, /Conta desactivada\./);
  assert.match(source, /loginIpLimiter/);
  assert.match(source, /loginIdentityLimiter/);
  assert.doesNotMatch(source, /enforceAdminOrigin/);
  assert.doesNotMatch(source, /LOGIN_ACCOUNT_LOCK_THRESHOLD/);
  assert.doesNotMatch(source, /LOGIN_IP_LOCK_THRESHOLD/);
});
