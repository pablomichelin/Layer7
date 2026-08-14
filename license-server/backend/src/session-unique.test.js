const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ADMIN = {
  id: 7,
  email: 'owner@example.com',
  name: 'Owner',
  is_owner: true,
  is_active: true,
  permissions: [],
};
const OTHER_ADMIN = {
  ...ADMIN,
  id: 8,
  email: 'other@example.com',
};
const REQ = {
  ip: '203.0.113.10',
  headers: { 'user-agent': 'session-unique-test' },
};

function normalizeSql(sql) {
  return String(sql).replace(/\s+/g, ' ').trim();
}

function createSessionStore() {
  const rows = [];
  let nextId = 1;
  const locks = new Map();

  function lockState(adminId) {
    if (!locks.has(adminId)) {
      locks.set(adminId, { owner: null, waiters: [] });
    }
    return locks.get(adminId);
  }

  function acquire(adminId, token) {
    const state = lockState(adminId);
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

  function release(adminId, token) {
    const state = lockState(adminId);
    if (state.owner !== token) {
      return;
    }
    state.owner = null;
    const next = state.waiters.shift();
    if (next) {
      next();
    }
  }

  function applyRevoke(adminId) {
    const now = new Date();
    for (const row of rows) {
      if (row.admin_id === adminId && row.revoked_at == null) {
        row.revoked_at = now;
      }
    }
  }

  function applyInsert(params) {
    const [adminId, tokenHash, createdAt, expiresAt, ip, userAgent] = params;
    const row = {
      id: nextId,
      admin_id: adminId,
      session_token_hash: tokenHash,
      created_at: createdAt,
      expires_at: expiresAt,
      last_seen_at: createdAt,
      revoked_at: null,
      ip_address: ip,
      user_agent: userAgent,
    };
    nextId += 1;
    rows.push(row);
    return row;
  }

  async function handleQuery(sql, params, token) {
    const normalized = normalizeSql(sql);

    if (normalized === 'BEGIN') {
      return { rows: [] };
    }

    if (normalized === 'COMMIT' || normalized === 'ROLLBACK') {
      if (token.lockedAdminId != null) {
        release(token.lockedAdminId, token);
        token.lockedAdminId = null;
      }
      return { rows: [] };
    }

    if (/SELECT id FROM admins WHERE id = \$1 FOR UPDATE/i.test(normalized)) {
      const adminId = params[0];
      await acquire(adminId, token);
      token.lockedAdminId = adminId;
      await new Promise((resolve) => setImmediate(resolve));
      return { rows: [{ id: adminId }] };
    }

    if (/UPDATE admin_sessions/i.test(normalized) && /revoked_at IS NULL/i.test(normalized)) {
      await new Promise((resolve) => setImmediate(resolve));
      applyRevoke(params[0]);
      return { rows: [] };
    }

    if (/INSERT INTO admin_sessions/i.test(normalized)) {
      await new Promise((resolve) => setImmediate(resolve));
      return { rows: [applyInsert(params)] };
    }

    throw new Error(`SQL inesperado no teste de sessao unica: ${normalized.slice(0, 180)}`);
  }

  const pool = {
    async query(sql, params = []) {
      return handleQuery(sql, params, {});
    },
    async connect() {
      const token = { lockedAdminId: null };
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
    activeFor(adminId) {
      return rows.filter((row) => row.admin_id === adminId && row.revoked_at == null);
    },
    all() {
      return rows.map((row) => ({ ...row }));
    },
  };
}

function loadSession(pool) {
  const dbPath = require.resolve('./db');
  const sessionPath = require.resolve('./session');
  delete require.cache[sessionPath];
  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: pool,
  };
  return require('./session');
}

test('P3-1 — dois createSession paralelos deixam uma unica linha active', async () => {
  const store = createSessionStore();
  const { createSession } = loadSession(store.pool);

  const [first, second] = await Promise.all([
    createSession(ADMIN, REQ),
    createSession(ADMIN, { ...REQ, ip: '203.0.113.11' }),
  ]);

  const active = store.activeFor(ADMIN.id);
  assert.equal(active.length, 1);
  assert.ok(first.token);
  assert.ok(second.token);
  assert.notEqual(first.token, second.token);
  assert.equal(
    active[0].session_token_hash.length,
    64,
    'hash SHA-256 hex do token'
  );
});

test('P3-1 — login sequencial revoga a sessao anterior do mesmo admin', async () => {
  const store = createSessionStore();
  const { createSession } = loadSession(store.pool);

  const first = await createSession(ADMIN, REQ);
  const second = await createSession(ADMIN, REQ);
  const active = store.activeFor(ADMIN.id);

  assert.equal(active.length, 1);
  assert.notEqual(first.token, second.token);
  assert.equal(active[0].id, second.metadata.session.id);
});

test('P3-1 — admins distintos podem ter sessoes activas em paralelo', async () => {
  const store = createSessionStore();
  const { createSession } = loadSession(store.pool);

  await Promise.all([
    createSession(ADMIN, REQ),
    createSession(OTHER_ADMIN, REQ),
  ]);

  assert.equal(store.activeFor(ADMIN.id).length, 1);
  assert.equal(store.activeFor(OTHER_ADMIN.id).length, 1);
});

test('P3-1 — createSession preserva TTL ocioso de 30 min', async () => {
  const store = createSessionStore();
  const { createSession } = loadSession(store.pool);
  const before = Date.now();
  const session = await createSession(ADMIN, REQ);
  const idleMs = session.metadata.session.expires_at.getTime() - before;
  const absoluteMs = session.metadata.session.absolute_expires_at.getTime()
    - session.metadata.session.created_at.getTime();

  assert.ok(idleMs >= 30 * 60 * 1000 - 1000);
  assert.ok(idleMs <= 30 * 60 * 1000 + 1000);
  assert.equal(absoluteMs, 8 * 60 * 60 * 1000);
});

test('P3-1 — createSession usa transaccao com lock do admin, nao unique parcial', () => {
  const source = fs.readFileSync(path.join(__dirname, 'session.js'), 'utf8');
  assert.match(source, /client\.query\('BEGIN'\)/);
  assert.match(source, /SELECT id FROM admins WHERE id = \$1 FOR UPDATE/);
  assert.match(source, /client\.query\('COMMIT'\)/);
  assert.match(source, /client\.query\('ROLLBACK'\)/);
  assert.doesNotMatch(
    source,
    /UNIQUE INDEX[\s\S]*admin_sessions\s*\(\s*admin_id\s*\)[\s\S]*revoked_at IS NULL/
  );
  assert.match(source, /SESSION_IDLE_TIMEOUT_MS = 30 \* 60 \* 1000/);
  assert.match(source, /SESSION_ABSOLUTE_TIMEOUT_MS = 8 \* 60 \* 60 \* 1000/);
  assert.match(source, /sameSite: 'strict'/);
  assert.match(source, /httpOnly: true/);
});
