const test = require('node:test');
const assert = require('node:assert/strict');

const {
  ARCHIVED_DENIED_CHECK_IN_LICENSE_SQL,
  VISIBLE_CHECK_IN_LICENSE_SQL,
  loadLicenseForCheckIn,
} = require('./check-in-lookup');
const { createActivationStateError } = require('./activation-policy');
const { getEffectiveLicenseState } = require('./license-state');

const KEY = 'abcdef0123456789abcdef0123456789';
const HW = 'a'.repeat(64);

function mockQueryable({ visibleRows = [], archivedRows = [] } = {}) {
  return {
    async query(sql, params) {
      assert.deepEqual(params, [KEY]);
      if (sql === VISIBLE_CHECK_IN_LICENSE_SQL) {
        return { rows: visibleRows };
      }
      if (sql === ARCHIVED_DENIED_CHECK_IN_LICENSE_SQL) {
        return { rows: archivedRows };
      }
      throw new Error('SQL inesperado no lookup de check-in');
    },
  };
}

test('P1-1 — chave inexistente continua 404', async () => {
  await assert.rejects(
    () => loadLicenseForCheckIn(mockQueryable(), KEY),
    (err) => err.status === 404 && err.message === 'Licenca nao encontrada.'
  );
});

test('P1-1 — visivel ganha sobre arquivada', async () => {
  const visible = { id: 10, license_key: KEY, status: 'active', archived_at: null };
  const archived = {
    id: 9,
    license_key: KEY,
    status: 'revoked',
    archived_at: '2026-08-14T12:00:00Z',
  };
  const license = await loadLicenseForCheckIn(
    mockQueryable({ visibleRows: [visible], archivedRows: [archived] }),
    KEY
  );
  assert.equal(license.id, 10);
});

test('P1-1 — arquivada revoked e visivel vazia devolve a linha antiga', async () => {
  const archived = {
    id: 9,
    license_key: KEY,
    status: 'revoked',
    archived_at: '2026-08-14T12:00:00Z',
    hardware_id: HW,
    expiry: '2027-12-31',
  };
  const license = await loadLicenseForCheckIn(
    mockQueryable({ archivedRows: [archived] }),
    KEY
  );
  assert.equal(license.id, 9);
  const error = createActivationStateError(license, getEffectiveLicenseState(license));
  assert.equal(error.status, 409);
  assert.equal(error.message, 'Licenca revogada.');
});

test('P1-1 — arquivada expired devolve 409 expired', async () => {
  const archived = {
    id: 8,
    license_key: KEY,
    status: 'expired',
    archived_at: '2026-08-14T12:00:00Z',
    hardware_id: HW,
    expiry: '2020-01-01',
  };
  const license = await loadLicenseForCheckIn(
    mockQueryable({ archivedRows: [archived] }),
    KEY
  );
  const error = createActivationStateError(license, getEffectiveLicenseState(license));
  assert.equal(error.status, 409);
  assert.equal(error.message, 'Licenca expirada.');
});

test('P1-1 — arquivada active com expiry passado e elegivel (SQL)', async () => {
  const archived = {
    id: 7,
    license_key: KEY,
    status: 'active',
    archived_at: '2026-08-14T12:00:00Z',
    hardware_id: HW,
    expiry: '2020-01-01',
  };
  const license = await loadLicenseForCheckIn(
    mockQueryable({ archivedRows: [archived] }),
    KEY
  );
  const error = createActivationStateError(license, getEffectiveLicenseState(license));
  assert.equal(error.status, 409);
  assert.equal(error.message, 'Licenca expirada.');
});

test('P1-1 — arquivada active vigente nao e devolvida pelo segundo SELECT', async () => {
  await assert.rejects(
    () => loadLicenseForCheckIn(mockQueryable({ archivedRows: [] }), KEY),
    (err) => err.status === 404
  );
});
