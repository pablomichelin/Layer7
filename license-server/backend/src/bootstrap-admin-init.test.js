const test = require('node:test');
const assert = require('node:assert/strict');
const {
  BOOTSTRAP_ALREADY_EXISTS_ERROR,
  BOOTSTRAP_COUNT_SQL,
  BOOTSTRAP_INSERT_SQL,
  BOOTSTRAP_LOCK_SQL,
  runBootstrapInitTransaction,
} = require('./bootstrap-admin-init');
const { OWNER_PERMISSIONS } = require('./admin-permissions');

function normalizeSql(sql) {
  return String(sql).replace(/\s+/g, ' ').trim();
}

function createSharedAdminStore() {
  let committed = [];
  let nextId = 1;
  let lockOwner = null;
  const waiters = [];

  function acquire(token) {
    return new Promise((resolve) => {
      const tryAcquire = () => {
        if (lockOwner === null) {
          lockOwner = token;
          resolve();
          return;
        }
        waiters.push(tryAcquire);
      };
      tryAcquire();
    });
  }

  function release(token) {
    if (lockOwner !== token) {
      return;
    }
    lockOwner = null;
    const next = waiters.shift();
    if (next) {
      next();
    }
  }

  function connect() {
    const token = {};
    let pending = null;
    let holdsLock = false;

    return {
      async query(sql, params = []) {
        const normalized = normalizeSql(sql);

        if (normalized === 'BEGIN') {
          pending = committed.map((row) => ({ ...row }));
          return { rows: [] };
        }

        if (normalized === normalizeSql(BOOTSTRAP_LOCK_SQL)) {
          await acquire(token);
          holdsLock = true;
          pending = committed.map((row) => ({ ...row }));
          return { rows: [] };
        }

        if (normalized === normalizeSql(BOOTSTRAP_COUNT_SQL)) {
          return { rows: [{ total: (pending || committed).length }] };
        }

        if (normalized === normalizeSql(BOOTSTRAP_INSERT_SQL)) {
          const [email, name, passwordHash, permissionsJson] = params;
          const row = {
            id: null,
            email,
            name,
            password_hash: passwordHash,
            is_owner: true,
            is_active: true,
            permissions: JSON.parse(permissionsJson),
            created_at: new Date('2026-08-14T15:00:00Z'),
            _new: true,
          };
          pending.push(row);
          return { rows: [row] };
        }

        if (normalized === 'COMMIT') {
          for (const row of (pending || []).filter((item) => item._new)) {
            row.id = nextId;
            nextId += 1;
            delete row._new;
            committed.push({ ...row });
          }
          pending = null;
          if (holdsLock) {
            release(token);
            holdsLock = false;
          }
          return { rows: [] };
        }

        if (normalized === 'ROLLBACK') {
          pending = null;
          if (holdsLock) {
            release(token);
            holdsLock = false;
          }
          return { rows: [] };
        }

        throw new Error(`SQL inesperado no bootstrap: ${normalized}`);
      },
    };
  }

  return {
    connect,
    list() {
      return committed.map((row) => ({ ...row }));
    },
  };
}

test('P1-4 — INSERT cria o primeiro admin ja como owner activo com *', async () => {
  const store = createSharedAdminStore();
  const admin = await runBootstrapInitTransaction(store.connect(), {
    email: 'owner@systemup.inf.br',
    name: 'Owner',
    passwordHash: 'hash-owner',
  });

  assert.equal(admin.email, 'owner@systemup.inf.br');
  assert.equal(admin.is_owner, true);
  assert.equal(admin.is_active, true);
  assert.deepEqual(admin.permissions, OWNER_PERMISSIONS);
  assert.equal(store.list().length, 1);
  assert.match(BOOTSTRAP_INSERT_SQL, /is_owner/);
  assert.match(BOOTSTRAP_INSERT_SQL, /TRUE, TRUE/);
  assert.match(BOOTSTRAP_LOCK_SQL, /SHARE ROW EXCLUSIVE/);
});

test('P1-4 — segundo init sequencial e recusado e nao cria outro owner', async () => {
  const store = createSharedAdminStore();
  await runBootstrapInitTransaction(store.connect(), {
    email: 'first@systemup.inf.br',
    name: 'First',
    passwordHash: 'hash-1',
  });

  await assert.rejects(
    () => runBootstrapInitTransaction(store.connect(), {
      email: 'second@systemup.inf.br',
      name: 'Second',
      passwordHash: 'hash-2',
    }),
    (err) => err.message === BOOTSTRAP_ALREADY_EXISTS_ERROR
  );

  assert.equal(store.list().length, 1);
  assert.equal(store.list()[0].email, 'first@systemup.inf.br');
});

test('P1-4 — dois inits concorrentes: um sucesso, um ja existe, um so owner', async () => {
  const store = createSharedAdminStore();
  const outcomes = await Promise.allSettled([
    runBootstrapInitTransaction(store.connect(), {
      email: 'alpha@systemup.inf.br',
      name: 'Alpha',
      passwordHash: 'hash-alpha',
    }),
    runBootstrapInitTransaction(store.connect(), {
      email: 'beta@systemup.inf.br',
      name: 'Beta',
      passwordHash: 'hash-beta',
    }),
  ]);

  const fulfilled = outcomes.filter((item) => item.status === 'fulfilled');
  const rejected = outcomes.filter((item) => item.status === 'rejected');

  assert.equal(fulfilled.length, 1);
  assert.equal(rejected.length, 1);
  assert.equal(rejected[0].reason.message, BOOTSTRAP_ALREADY_EXISTS_ERROR);
  assert.equal(store.list().length, 1);
  assert.equal(store.list()[0].is_owner, true);
  assert.equal(store.list()[0].is_active, true);
  assert.ok(
    ['alpha@systemup.inf.br', 'beta@systemup.inf.br'].includes(store.list()[0].email)
  );
});
