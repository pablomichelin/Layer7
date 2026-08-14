const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const ACCOUNT_EMAIL = 'owner@example.com';
const CLIENT_IP = '203.0.113.10';
const ACCOUNT_LOCK_THRESHOLD = 5;
const IP_LOCK_THRESHOLD = 10;
const GUARD_WINDOW_MS = 15 * 60 * 1000;
const LOCK_DURATION_MS = 15 * 60 * 1000;

function keyOf(scopeType, scopeKey) {
  return `${scopeType}\0${scopeKey}`;
}

function createGuardStore() {
  const rows = new Map();
  let upsertTail = Promise.resolve();

  function snapshot(scopeType, scopeKey) {
    const current = rows.get(keyOf(scopeType, scopeKey));
    return current ? { ...current } : null;
  }

  function applyExcludedWrite(params) {
    const [scopeType, scopeKey, failureCount, firstFailureAt, lastFailureAt, lockedUntil] = params;
    const next = {
      scope_type: scopeType,
      scope_key: scopeKey,
      failure_count: Number(failureCount),
      first_failure_at: firstFailureAt,
      last_failure_at: lastFailureAt,
      locked_until: lockedUntil,
    };
    rows.set(keyOf(scopeType, scopeKey), next);
    return next;
  }

  function applyAtomicUpsert(params) {
    const [scopeType, scopeKey, now, threshold, lockedUntilCandidate, windowMs] = params;
    const existing = snapshot(scopeType, scopeKey);
    const first = existing?.first_failure_at ? new Date(existing.first_failure_at) : null;
    const at = now instanceof Date ? now : new Date(now);
    const withinWindow = Boolean(first && (at.getTime() - first.getTime()) <= Number(windowMs));
    const failureCount = withinWindow ? existing.failure_count + 1 : 1;
    const next = {
      scope_type: scopeType,
      scope_key: scopeKey,
      failure_count: failureCount,
      first_failure_at: withinWindow ? first : at,
      last_failure_at: at,
      locked_until: failureCount >= Number(threshold) ? lockedUntilCandidate : null,
    };
    rows.set(keyOf(scopeType, scopeKey), next);
    return next;
  }

  function enqueueUpsert(apply) {
    const run = upsertTail.then(apply, apply);
    upsertTail = run.catch(() => {});
    return run;
  }

  const pool = {
    async query(sql, params = []) {
      const normalized = String(sql).replace(/\s+/g, ' ').trim();
      const isSelect = /^SELECT /i.test(normalized) && /FROM admin_login_guards/i.test(normalized);
      const isUpsert = /INSERT INTO admin_login_guards/i.test(normalized);

      if (isSelect) {
        const current = snapshot(params[0], params[1]);
        await new Promise((resolve) => setImmediate(resolve));
        return { rows: current ? [current] : [] };
      }

      if (isUpsert) {
        const usesExcludedCount = /failure_count = EXCLUDED\.failure_count/i.test(normalized);
        const usesAtomicInc = /admin_login_guards\.failure_count \+ 1/i.test(normalized);
        const next = await enqueueUpsert(() => (
          usesAtomicInc && !usesExcludedCount
            ? applyAtomicUpsert(params)
            : applyExcludedWrite(params)
        ));
        return { rows: [{ ...next }] };
      }

      throw new Error(`unexpected SQL in login-guard test: ${normalized.slice(0, 160)}`);
    },
  };

  return {
    pool,
    get(scopeType, scopeKey) {
      return snapshot(scopeType, scopeKey);
    },
  };
}

function loadAdminSurface(pool) {
  const dbPath = require.resolve('./db');
  const surfacePath = require.resolve('./admin-surface');
  delete require.cache[surfacePath];
  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: pool,
  };
  return require('./admin-surface');
}

test('P2-4 — 10 registerLoginFailure paralelos incrementam até 10 e trancam conta e IP', async () => {
  const store = createGuardStore();
  const { registerLoginFailure } = loadAdminSurface(store.pool);
  const started = Date.now();

  await Promise.all(Array.from({ length: 10 }, () => (
    registerLoginFailure({
      email: ACCOUNT_EMAIL,
      req: { ip: CLIENT_IP },
    })
  )));

  const account = store.get('account', ACCOUNT_EMAIL);
  const ip = store.get('ip', CLIENT_IP);

  assert.equal(account.failure_count, 10);
  assert.equal(ip.failure_count, 10);
  assert.ok(account.locked_until, 'account lock after 5/15min contract');
  assert.ok(ip.locked_until, 'IP lock after 10/15min contract');

  const accountLockMs = new Date(account.locked_until).getTime() - started;
  const ipLockMs = new Date(ip.locked_until).getTime() - started;
  assert.ok(accountLockMs >= LOCK_DURATION_MS - 1000);
  assert.ok(accountLockMs <= LOCK_DURATION_MS + 5000);
  assert.ok(ipLockMs >= LOCK_DURATION_MS - 1000);
  assert.ok(ipLockMs <= LOCK_DURATION_MS + 5000);
});

test('P2-4 — contrato sequencial: 4 falhas de conta nao trancam; a 5.a tranca 15min', async () => {
  const store = createGuardStore();
  const { registerLoginFailure } = loadAdminSurface(store.pool);
  const req = { ip: '203.0.113.11' };

  for (let i = 0; i < ACCOUNT_LOCK_THRESHOLD - 1; i += 1) {
    await registerLoginFailure({ email: 'seq@example.com', req });
  }

  const beforeLock = store.get('account', 'seq@example.com');
  assert.equal(beforeLock.failure_count, ACCOUNT_LOCK_THRESHOLD - 1);
  assert.equal(beforeLock.locked_until, null);

  const beforeFifth = Date.now();
  await registerLoginFailure({ email: 'seq@example.com', req });
  const locked = store.get('account', 'seq@example.com');
  assert.equal(locked.failure_count, ACCOUNT_LOCK_THRESHOLD);
  assert.ok(locked.locked_until);
  const lockMs = new Date(locked.locked_until).getTime() - beforeFifth;
  assert.ok(lockMs >= LOCK_DURATION_MS - 1000);
  assert.ok(lockMs <= LOCK_DURATION_MS + 5000);
  assert.equal(store.get('ip', '203.0.113.11').failure_count, ACCOUNT_LOCK_THRESHOLD);
  assert.equal(store.get('ip', '203.0.113.11').locked_until, null);
});

test('P2-4 — janela de 15min expirada reinicia a contagem', async () => {
  const store = createGuardStore();
  const { registerLoginFailure } = loadAdminSurface(store.pool);
  const req = { ip: '203.0.113.12' };
  const stale = new Date(Date.now() - GUARD_WINDOW_MS - 1000);

  await store.pool.query(
    `INSERT INTO admin_login_guards (
        scope_type, scope_key, failure_count, first_failure_at, last_failure_at, locked_until
      ) VALUES ($1, $2, $3, $4, $5, $6)
      ON CONFLICT (scope_type, scope_key) DO UPDATE SET failure_count = EXCLUDED.failure_count`,
    ['account', 'stale@example.com', 4, stale, stale, null]
  );

  await registerLoginFailure({ email: 'stale@example.com', req });
  const account = store.get('account', 'stale@example.com');
  assert.equal(account.failure_count, 1);
  assert.equal(account.locked_until, null);
});

test('P2-4 — updateLoginGuard deixa de gravar EXCLUDED.failure_count', () => {
  const source = require('node:fs').readFileSync(
    path.join(__dirname, 'admin-surface.js'),
    'utf8'
  );
  assert.match(source, /admin_login_guards\.failure_count \+ 1/);
  assert.doesNotMatch(
    source,
    /failure_count = EXCLUDED\.failure_count/
  );
  assert.match(source, /LOGIN_ACCOUNT_LOCK_THRESHOLD = 5/);
  assert.match(source, /LOGIN_IP_LOCK_THRESHOLD = 10/);
  assert.match(source, /LOGIN_GUARD_WINDOW_MS = 15 \* 60 \* 1000/);
  assert.match(source, /LOGIN_LOCK_DURATION_MS = 15 \* 60 \* 1000/);
});
