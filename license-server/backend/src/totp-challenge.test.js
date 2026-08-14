const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('crypto');
const {
  createTotpChallengeToken,
  parseTotpChallengeToken,
} = require('./totp');

const SECRET = 'p0-2-challenge-hmac-secret';
const ADMIN_ID = 7;
const OTHER_ADMIN_ID = 8;

function normalizeSql(sql) {
  return String(sql).replace(/\s+/g, ' ').trim();
}

function createChallengeStore() {
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

    if (normalized === 'BEGIN') {
      return { rows: [] };
    }

    if (normalized === 'COMMIT' || normalized === 'ROLLBACK') {
      if (token.lockedJti != null) {
        release(token.lockedJti, token);
        token.lockedJti = null;
      }
      return { rows: [] };
    }

    if (/UPDATE admin_totp_challenges/i.test(normalized) && /used_at IS NULL/i.test(normalized) && /admin_id = \$1/i.test(normalized)) {
      const adminId = params[0];
      const now = new Date();
      for (const row of rows) {
        if (row.admin_id === adminId && row.used_at == null) {
          row.used_at = now;
        }
      }
      return { rows: [] };
    }

    if (/INSERT INTO admin_totp_challenges/i.test(normalized)) {
      const [jti, adminId, expiresAt] = params;
      rows.push({
        jti,
        admin_id: adminId,
        expires_at: expiresAt,
        used_at: null,
        created_at: new Date(),
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
      const jti = params[0];
      const row = rows.find((item) => item.jti === jti);
      if (row && row.used_at == null) {
        row.used_at = new Date();
      }
      return { rows: [] };
    }

    throw new Error(`SQL inesperado no teste P0-2: ${normalized.slice(0, 180)}`);
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
    unusedFor(adminId) {
      return rows.filter((row) => row.admin_id === adminId && row.used_at == null);
    },
    byJti(jti) {
      return rows.find((row) => row.jti === jti) || null;
    },
    all() {
      return rows.map((row) => ({ ...row }));
    },
  };
}

function loadChallenge(pool) {
  const dbPath = require.resolve('./db');
  const challengePath = require.resolve('./totp-challenge');
  delete require.cache[challengePath];
  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: pool,
  };
  return require('./totp-challenge');
}

test('P0-2 — issue grava jti e invalida desafios unused do mesmo admin', async () => {
  const store = createChallengeStore();
  const { issueTotpChallenge } = loadChallenge(store.pool);

  const first = await issueTotpChallenge(ADMIN_ID, SECRET);
  const firstJti = parseTotpChallengeToken(first, SECRET).jti;
  const second = await issueTotpChallenge(ADMIN_ID, SECRET);
  const secondJti = parseTotpChallengeToken(second, SECRET).jti;

  assert.notEqual(firstJti, secondJti);
  assert.ok(store.byJti(firstJti).used_at);
  assert.equal(store.byJti(secondJti).used_at, null);
  assert.equal(store.unusedFor(ADMIN_ID).length, 1);
});

test('P0-2 — consume marca used_at e recusa replay', async () => {
  const store = createChallengeStore();
  const { issueTotpChallenge, consumeTotpChallenge } = loadChallenge(store.pool);
  const token = await issueTotpChallenge(ADMIN_ID, SECRET);
  const { jti } = parseTotpChallengeToken(token, SECRET);

  assert.equal(await consumeTotpChallenge({ jti, adminId: ADMIN_ID }), true);
  assert.ok(store.byJti(jti).used_at);
  assert.equal(await consumeTotpChallenge({ jti, adminId: ADMIN_ID }), false);
});

test('P0-2 — jti HMAC-válido inexistente ou de outro admin não consome', async () => {
  const store = createChallengeStore();
  const { issueTotpChallenge, consumeTotpChallenge } = loadChallenge(store.pool);
  const token = await issueTotpChallenge(ADMIN_ID, SECRET);
  const { jti } = parseTotpChallengeToken(token, SECRET);
  const forged = createTotpChallengeToken(ADMIN_ID, SECRET);
  const forgedJti = parseTotpChallengeToken(forged, SECRET).jti;

  assert.equal(await consumeTotpChallenge({ jti: forgedJti, adminId: ADMIN_ID }), false);
  assert.equal(await consumeTotpChallenge({ jti, adminId: OTHER_ADMIN_ID }), false);
  assert.equal(store.byJti(jti).used_at, null);
  assert.equal(store.byJti(forgedJti), null);
});

test('P0-2 — dois consume paralelos do mesmo jti deixam um sucesso', async () => {
  const store = createChallengeStore();
  const { issueTotpChallenge, consumeTotpChallenge } = loadChallenge(store.pool);
  const token = await issueTotpChallenge(ADMIN_ID, SECRET);
  const { jti } = parseTotpChallengeToken(token, SECRET);

  const [first, second] = await Promise.all([
    consumeTotpChallenge({ jti, adminId: ADMIN_ID }),
    consumeTotpChallenge({ jti, adminId: ADMIN_ID }),
  ]);

  assert.equal([first, second].filter(Boolean).length, 1);
  assert.ok(store.byJti(jti).used_at);
});

test('P0-2 — desafio anterior unused fica inválido após novo issue', async () => {
  const store = createChallengeStore();
  const { issueTotpChallenge, consumeTotpChallenge } = loadChallenge(store.pool);
  const previous = await issueTotpChallenge(ADMIN_ID, SECRET);
  const current = await issueTotpChallenge(ADMIN_ID, SECRET);
  const previousJti = parseTotpChallengeToken(previous, SECRET).jti;
  const currentJti = parseTotpChallengeToken(current, SECRET).jti;

  assert.equal(await consumeTotpChallenge({ jti: previousJti, adminId: ADMIN_ID }), false);
  assert.equal(await consumeTotpChallenge({ jti: currentJti, adminId: ADMIN_ID }), true);
});

test('P0-2 — consume recusa jti malformado sem SQL', async () => {
  const store = createChallengeStore();
  const { consumeTotpChallenge } = loadChallenge(store.pool);
  assert.equal(await consumeTotpChallenge({ jti: 'not-a-jti', adminId: ADMIN_ID }), false);
  assert.equal(await consumeTotpChallenge({ jti: crypto.randomBytes(8).toString('hex'), adminId: ADMIN_ID }), false);
});
