const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('crypto');
const bcrypt = require('bcryptjs');
const {
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
} = require('./admin-surface');
const {
  createTotpChallengeToken,
  generateTotp,
  parseTotpChallengeToken,
} = require('./totp');

const REAL_PASSWORD = 'correct-password-12';
const REAL_HASH = bcrypt.hashSync(REAL_PASSWORD, 4);
const HMAC_SECRET = 'p0-2-route-hmac-secret-32-bytes-min';
const TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

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

function normalizeSql(sql) {
  return String(sql).replace(/\s+/g, ' ').trim();
}

function adminRow(overrides = {}) {
  return {
    id: 7,
    email: 'owner@example.com',
    password_hash: REAL_HASH,
    is_active: true,
    totp_enabled: true,
    totp_secret: TOTP_SECRET,
    name: 'Owner',
    is_owner: true,
    permissions: ['*'],
    ...overrides,
  };
}

function createChallengeStore(admins) {
  const rows = [];
  const locks = new Map();

  function lockState(jti) {
    if (!locks.has(jti)) {
      locks.set(jti, { owner: null, waiters: [] });
    }
    return locks.get(jti);
  }

  function acquire(jti, token) {
    const state = lockState(jti);
    return new Promise((resolve) => {
      const tryAcquire = () => {
        if (state.owner === null) {
          state.owner = token;
          resolve();
          return;
        }
        state.waiters.push(tryAcquire);
      };
      tryAcquire();
    });
  }

  function release(jti, token) {
    const state = lockState(jti);
    if (state.owner !== token) {
      return;
    }
    state.owner = null;
    const next = state.waiters.shift();
    if (next) {
      next();
    }
  }

  async function handleQuery(sql, params, token) {
    const normalized = normalizeSql(sql);

    if (/SELECT \* FROM admins WHERE LOWER\(email\)/i.test(normalized)) {
      const email = String(params?.[0] || '').toLowerCase();
      return { rows: admins.filter((admin) => admin.email.toLowerCase() === email) };
    }

    if (/SELECT \* FROM admins WHERE id = \$1/i.test(normalized)) {
      return { rows: admins.filter((admin) => admin.id === params[0]) };
    }

    if (normalized === 'BEGIN' || normalized === 'COMMIT' || normalized === 'ROLLBACK') {
      if ((normalized === 'COMMIT' || normalized === 'ROLLBACK') && token.lockedJti != null) {
        release(token.lockedJti, token);
        token.lockedJti = null;
      }
      return { rows: [] };
    }

    if (/UPDATE admin_totp_challenges/i.test(normalized) && /admin_id = \$1/i.test(normalized) && /used_at IS NULL/i.test(normalized)) {
      const now = new Date();
      for (const row of rows) {
        if (row.admin_id === params[0] && row.used_at == null) {
          row.used_at = now;
        }
      }
      return { rows: [] };
    }

    if (/INSERT INTO admin_totp_challenges/i.test(normalized)) {
      rows.push({
        jti: params[0],
        admin_id: params[1],
        expires_at: params[2],
        used_at: null,
      });
      return { rows: [] };
    }

    if (/SELECT jti, admin_id, expires_at, used_at FROM admin_totp_challenges WHERE jti = \$1 FOR UPDATE/i.test(normalized)) {
      const jti = params[0];
      await acquire(jti, token);
      token.lockedJti = jti;
      await new Promise((resolve) => setImmediate(resolve));
      const row = rows.find((item) => item.jti === jti);
      return { rows: row ? [{ ...row }] : [] };
    }

    if (/UPDATE admin_totp_challenges/i.test(normalized) && /SET used_at = NOW\(\)/i.test(normalized) && /jti = \$1/i.test(normalized)) {
      const row = rows.find((item) => item.jti === params[0]);
      if (row && row.used_at == null) {
        row.used_at = new Date();
      }
      return { rows: [] };
    }

    throw new Error(`SQL inesperado no teste de rota P0-2: ${normalized.slice(0, 180)}`);
  }

  const pool = {
    async query(sql, params = []) {
      return handleQuery(sql, params, {});
    },
    async connect() {
      const token = { lockedJti: null };
      return {
        async query(sql, params = []) {
          return handleQuery(sql, params, token);
        },
        release() {},
      };
    },
  };

  return {
    pool,
    byJti(jti) {
      return rows.find((row) => row.jti === jti) || null;
    },
  };
}

function loadAuthRouter({ admins, activeLock = null }) {
  const store = createChallengeStore(admins);
  const dbPath = require.resolve('./db');
  const surfacePath = require.resolve('./admin-surface');
  const sessionPath = require.resolve('./session');
  const challengePath = require.resolve('./totp-challenge');
  const authRoutePath = require.resolve('./routes/auth');
  const realSurface = require('./admin-surface');
  const realSession = require('./session');
  const sessions = [];
  const failures = [];
  const resets = [];

  delete require.cache[challengePath];
  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: store.pool,
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
        failures.push(args.email);
        return [];
      },
      resetLoginProtection: async (args) => {
        resets.push(args.email);
      },
      auditAdminEvent: async () => {},
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
          token: `session-${sessions.length}`,
          metadata: {
            admin,
            session: {
              id: sessions.length,
              created_at: now,
              last_seen_at: now,
              expires_at: new Date(now.getTime() + 30 * 60 * 1000),
              absolute_expires_at: new Date(now.getTime() + 8 * 60 * 60 * 1000),
            },
          },
        };
      },
      setSessionCookie: () => {},
      clearSessionCookie: () => {},
    },
  };

  delete require.cache[authRoutePath];
  const router = require('./routes/auth');
  return {
    router,
    store,
    sessions,
    failures,
    resets,
  };
}

function findHandler(router, routePath) {
  const layer = router.stack.find((item) => (
    item.route && item.route.path === routePath && item.route.methods.post
  ));
  assert.ok(layer, `rota POST ${routePath} tem de existir`);
  return layer.route.stack[layer.route.stack.length - 1].handle;
}

async function post(router, routePath, body) {
  const handler = findHandler(router, routePath);
  const res = createResponse();
  await handler({
    body,
    ip: '203.0.113.20',
    headers: { 'user-agent': 'p0-2-route-test' },
    originalUrl: `/api/auth${routePath}`,
    path: `/api/auth${routePath}`,
  }, res, (err) => {
    if (err) {
      throw err;
    }
  });
  return res;
}

function withHmacSecret(fn) {
  return async () => {
    const previous = process.env.ADMIN_BEARER_JWT_SECRET;
    process.env.ADMIN_BEARER_JWT_SECRET = HMAC_SECRET;
    try {
      await fn();
    } finally {
      if (previous === undefined) {
        delete process.env.ADMIN_BEARER_JWT_SECRET;
      } else {
        process.env.ADMIN_BEARER_JWT_SECRET = previous;
      }
    }
  };
}

test('P0-2 — password OK + TOTP OK cria sessão e marca used_at', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const login = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });
  assert.equal(login.body.status, 'totp_required');
  const parsed = parseTotpChallengeToken(login.body.challenge_token, HMAC_SECRET);
  assert.match(parsed.jti, /^[0-9a-f]{32}$/);

  const totp = await post(harness.router, '/login/totp', {
    challenge_token: login.body.challenge_token,
    code: generateTotp(TOTP_SECRET),
  });

  assert.equal(totp.statusCode, 200);
  assert.equal(harness.sessions.length, 1);
  assert.ok(harness.store.byJti(parsed.jti).used_at);
  assert.deepEqual(harness.resets, ['owner@example.com']);
}));

test('P0-2 — replay do mesmo token após sucesso devolve 401 genérico', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const login = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });
  const body = {
    challenge_token: login.body.challenge_token,
    code: generateTotp(TOTP_SECRET),
  };

  const first = await post(harness.router, '/login/totp', body);
  const replay = await post(harness.router, '/login/totp', body);

  assert.equal(first.statusCode, 200);
  assert.equal(replay.statusCode, 401);
  assert.deepEqual(replay.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.sessions.length, 1);
  assert.equal(harness.failures.includes('owner@example.com'), true);
}));

test('P0-2 — HMAC válido com jti inexistente ou forjado devolve 401 sem sessão', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const forged = createTotpChallengeToken(7, HMAC_SECRET);
  const res = await post(harness.router, '/login/totp', {
    challenge_token: forged,
    code: generateTotp(TOTP_SECRET),
  });

  assert.equal(res.statusCode, 401);
  assert.deepEqual(res.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.sessions.length, 0);
  assert.equal(harness.store.byJti(parseTotpChallengeToken(forged, HMAC_SECRET).jti), null);
}));

test('P0-2 — token antigo sem jti devolve 401 genérico', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const payload = Buffer.from(JSON.stringify({
    admin_id: 7,
    exp: Date.now() + 60_000,
  })).toString('base64url');
  const sig = crypto.createHmac('sha256', HMAC_SECRET).update(payload).digest('base64url');
  const res = await post(harness.router, '/login/totp', {
    challenge_token: `${payload}.${sig}`,
    code: generateTotp(TOTP_SECRET),
  });

  assert.equal(res.statusCode, 401);
  assert.deepEqual(res.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.sessions.length, 0);
}));

test('P0-2 — dois POST /login/totp paralelos com o mesmo token deixam uma sessão', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const login = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });
  const body = {
    challenge_token: login.body.challenge_token,
    code: generateTotp(TOTP_SECRET),
  };

  const [first, second] = await Promise.all([
    post(harness.router, '/login/totp', body),
    post(harness.router, '/login/totp', body),
  ]);

  const statuses = [first.statusCode, second.statusCode].sort();
  assert.deepEqual(statuses, [200, 401]);
  assert.equal(harness.sessions.length, 1);
  const failed = first.statusCode === 401 ? first : second;
  assert.deepEqual(failed.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
}));

test('P0-2 — novo /login invalida o desafio unused anterior', withHmacSecret(async () => {
  const harness = loadAuthRouter({ admins: [adminRow()] });
  const firstLogin = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });
  const secondLogin = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });

  const stale = await post(harness.router, '/login/totp', {
    challenge_token: firstLogin.body.challenge_token,
    code: generateTotp(TOTP_SECRET),
  });
  const current = await post(harness.router, '/login/totp', {
    challenge_token: secondLogin.body.challenge_token,
    code: generateTotp(TOTP_SECRET),
  });

  assert.equal(stale.statusCode, 401);
  assert.deepEqual(stale.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(current.statusCode, 200);
  assert.equal(harness.sessions.length, 1);
}));

test('P0-2 — conta desactivada no segundo factor continua 401 genérico sem sessão', withHmacSecret(async () => {
  const harness = loadAuthRouter({
    admins: [adminRow({ is_active: false })],
  });
  const login = await post(harness.router, '/login', {
    email: 'owner@example.com',
    password: REAL_PASSWORD,
  });
  assert.equal(login.statusCode, 401);
  assert.equal(login.body.status, undefined);

  const issued = createTotpChallengeToken(7, HMAC_SECRET);
  const totp = await post(harness.router, '/login/totp', {
    challenge_token: issued,
    code: generateTotp(TOTP_SECRET),
  });
  assert.equal(totp.statusCode, 401);
  assert.deepEqual(totp.body, { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
  assert.equal(harness.sessions.length, 0);
}));
