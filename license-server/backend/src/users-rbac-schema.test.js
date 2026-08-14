const test = require('node:test');
const assert = require('node:assert/strict');
const {
  COUNT_OWNERS_SQL,
  ENSURE_USERS_RBAC_COLUMNS_SQL,
  MULTIPLE_OWNERS_WARNING,
  PROMOTE_LEGACY_OWNER_SQL,
  REPAIR_OWNER_PERMISSIONS_SQL,
  ensureUsersRbacSchema,
} = require('./users-rbac-schema');
const { OWNER_PERMISSIONS } = require('./admin-permissions');

function normalizeSql(sql) {
  return String(sql).replace(/\s+/g, ' ').trim();
}

function createRbacQueryable(rows) {
  return {
    rows,
    async query(sql, params = []) {
      const normalized = normalizeSql(sql);

      if (normalized === normalizeSql(ENSURE_USERS_RBAC_COLUMNS_SQL)) {
        return { rows: [] };
      }

      if (normalized === normalizeSql(PROMOTE_LEGACY_OWNER_SQL)) {
        if (rows.some((row) => row.is_owner === true)) {
          return { rows: [], rowCount: 0 };
        }
        const target = [...rows].sort((left, right) => left.id - right.id)[0];
        if (!target) {
          return { rows: [], rowCount: 0 };
        }
        target.is_owner = true;
        target.permissions = JSON.parse(params[0]);
        return { rows: [{ id: target.id }], rowCount: 1 };
      }

      if (normalized === normalizeSql(REPAIR_OWNER_PERMISSIONS_SQL)) {
        const permissions = JSON.parse(params[0]);
        for (const row of rows) {
          if (row.is_owner === true) {
            const current = row.permissions;
            const missingStar = !Array.isArray(current) || !current.includes('*');
            if (missingStar) {
              row.permissions = permissions;
            }
          }
        }
        return { rows: [] };
      }

      if (normalized === normalizeSql(COUNT_OWNERS_SQL)) {
        return {
          rows: [{ owner_count: rows.filter((row) => row.is_owner === true).length }],
        };
      }

      throw new Error(`SQL inesperado no RBAC: ${normalized}`);
    },
  };
}

test('P2-1 — SQL de promocao legado e deterministica (ORDER BY id LIMIT 1)', () => {
  assert.match(PROMOTE_LEGACY_OWNER_SQL, /ORDER BY id ASC/);
  assert.match(PROMOTE_LEGACY_OWNER_SQL, /LIMIT 1/);
  assert.match(PROMOTE_LEGACY_OWNER_SQL, /NOT EXISTS/);
  assert.doesNotMatch(PROMOTE_LEGACY_OWNER_SQL, /WHERE NOT EXISTS \([\s\S]*\)\s*$/);
});

test('P2-1 — restart sem owner promove so o id minimo', async () => {
  const rows = [
    { id: 7, email: 'late@example.com', is_owner: false, permissions: [] },
    { id: 3, email: 'first@example.com', is_owner: false, permissions: [] },
    { id: 5, email: 'mid@example.com', is_owner: false, permissions: [] },
  ];
  const warnings = [];
  const result = await ensureUsersRbacSchema(createRbacQueryable(rows), {
    warn: (message) => warnings.push(message),
  });

  assert.equal(result.promotedId, 3);
  assert.equal(result.ownerCount, 1);
  assert.equal(rows.find((row) => row.id === 3).is_owner, true);
  assert.deepEqual(rows.find((row) => row.id === 3).permissions, OWNER_PERMISSIONS);
  assert.equal(rows.find((row) => row.id === 5).is_owner, false);
  assert.equal(rows.find((row) => row.id === 7).is_owner, false);
  assert.deepEqual(warnings, []);
});

test('P2-1 — segundo restart com owner existente nao promove mais ninguem', async () => {
  const rows = [
    { id: 1, email: 'owner@example.com', is_owner: true, permissions: ['*'] },
    { id: 2, email: 'tech@example.com', is_owner: false, permissions: ['licenses.read'] },
    { id: 3, email: 'other@example.com', is_owner: false, permissions: [] },
  ];
  const first = await ensureUsersRbacSchema(createRbacQueryable(rows));
  const second = await ensureUsersRbacSchema(createRbacQueryable(rows));

  assert.equal(first.promotedId, null);
  assert.equal(second.promotedId, null);
  assert.equal(first.ownerCount, 1);
  assert.equal(second.ownerCount, 1);
  assert.equal(rows.filter((row) => row.is_owner).length, 1);
  assert.equal(rows.find((row) => row.id === 2).is_owner, false);
  assert.equal(rows.find((row) => row.id === 3).is_owner, false);
});

test('P2-1 — multiplos owners geram alerta e nao promovem nem demovem', async () => {
  const rows = [
    { id: 1, email: 'owner-a@example.com', is_owner: true, permissions: ['*'] },
    { id: 2, email: 'owner-b@example.com', is_owner: true, permissions: ['*'] },
    { id: 3, email: 'tech@example.com', is_owner: false, permissions: [] },
  ];
  const warnings = [];
  const result = await ensureUsersRbacSchema(createRbacQueryable(rows), {
    warn: (message) => warnings.push(message),
  });

  assert.equal(result.promotedId, null);
  assert.equal(result.ownerCount, 2);
  assert.equal(rows.find((row) => row.id === 1).is_owner, true);
  assert.equal(rows.find((row) => row.id === 2).is_owner, true);
  assert.equal(rows.find((row) => row.id === 3).is_owner, false);
  assert.equal(warnings.length, 1);
  assert.match(warnings[0], new RegExp(MULTIPLE_OWNERS_WARNING.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')));
  assert.match(warnings[0], /owner_count=2/);
});
