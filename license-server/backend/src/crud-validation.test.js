const test = require('node:test');
const assert = require('node:assert/strict');

const {
  isLicenseExpired,
  normalizeFeatures,
  normalizeStoredHardwareId,
  parseActivatePayload,
  parseLicenseCreatePayload,
  parseLicensesListQuery,
  parseAuditListQuery,
  FEATURES_MAX_BYTES,
} = require('./crud-validation');

const LICENSE_KEY = 'ABCDEF0123456789ABCDEF0123456789';
const HARDWARE_ID = 'A'.repeat(64);

function assertHttpError(fn, status, message) {
  assert.throws(
    fn,
    (error) => error?.status === status && error?.message === message
  );
}

test('parseActivatePayload normalizes key and hardware_id', () => {
  const payload = parseActivatePayload({
    key: ` ${LICENSE_KEY} `,
    hardware_id: ` ${HARDWARE_ID} `,
  });

  assert.equal(payload.key, LICENSE_KEY.toLowerCase());
  assert.equal(payload.hardwareId, HARDWARE_ID.toLowerCase());
});

test('parseActivatePayload rejects non-object payloads', () => {
  assertHttpError(() => parseActivatePayload(null), 400, 'Payload invalido.');
  assertHttpError(() => parseActivatePayload([]), 400, 'Payload invalido.');
});

test('parseActivatePayload rejects unexpected fields', () => {
  assertHttpError(
    () => parseActivatePayload({
      key: LICENSE_KEY,
      hardware_id: HARDWARE_ID,
      extra: true,
    }),
    400,
    'Payload contem campos nao permitidos: extra'
  );
});

test('parseActivatePayload rejects invalid license keys', () => {
  assertHttpError(
    () => parseActivatePayload({
      key: 'not-a-key',
      hardware_id: HARDWARE_ID,
    }),
    400,
    'key invalida.'
  );
});

test('parseActivatePayload rejects invalid hardware IDs', () => {
  assertHttpError(
    () => parseActivatePayload({
      key: LICENSE_KEY,
      hardware_id: 'not-hardware',
    }),
    400,
    'hardware_id invalido.'
  );
});

test('normalizeStoredHardwareId returns null for unusable values', () => {
  assert.equal(normalizeStoredHardwareId(null), null);
  assert.equal(normalizeStoredHardwareId(''), null);
  assert.equal(normalizeStoredHardwareId('not-hardware'), null);
});

test('isLicenseExpired treats PostgreSQL Date expiry values as expired', () => {
  assert.equal(
    isLicenseExpired({ expiry: new Date('2026-03-31T00:00:00.000Z') }),
    true
  );
});

test('isLicenseExpired treats YYYY-MM-DD strings as expired', () => {
  assert.equal(isLicenseExpired({ expiry: '2000-01-01' }), true);
  assert.equal(isLicenseExpired({ expiry: '2999-12-31' }), false);
});

test('normalizeFeatures defaults to base and applies T1 full→base', () => {
  assert.equal(normalizeFeatures(undefined), 'base');
  assert.equal(normalizeFeatures(''), 'base');
  assert.equal(normalizeFeatures('full'), 'base');
  assert.equal(normalizeFeatures('FULL'), 'base');
  assert.equal(normalizeFeatures('base,identity'), 'base,identity');
  assert.equal(normalizeFeatures(' identity , MITM , base '), 'base,identity,mitm');
  assert.equal(normalizeFeatures('full,identity'), 'base,identity');
});

test('normalizeFeatures rejects unknown tokens and oversized CSV (P1)', () => {
  assertHttpError(
    () => normalizeFeatures('base,reports'),
    400,
    'Features invalidas: token desconhecido "reports". Permitidos: base, identity, mitm (legado: full).'
  );
  assert.equal(FEATURES_MAX_BYTES, 63);
  assertHttpError(
    () => normalizeFeatures('x'.repeat(FEATURES_MAX_BYTES + 1)),
    400,
    `Features excedem ${FEATURES_MAX_BYTES} bytes (ADR-0025 P1).`
  );
});

test('parseLicenseCreatePayload normalizes features SKU', () => {
  const payload = parseLicenseCreatePayload({
    customer_id: 1,
    expiry: '2030-12-31',
    features: 'full',
  });
  assert.equal(payload.features, 'base');
});

test('parseLicensesListQuery accepts bound and expiring_within_days filters', () => {
  const query = parseLicensesListQuery({
    page: '1',
    limit: '20',
    bound: 'yes',
    expiring_within_days: '30',
    status: 'active',
  });

  assert.equal(query.bound, true);
  assert.equal(query.expiringWithinDays, 30);
  assert.equal(query.status, 'active');
});

test('parseLicensesListQuery rejects invalid bound and oversized expiring window', () => {
  assert.throws(
    () => parseLicensesListQuery({ bound: 'maybe' }),
    (error) => error.status === 400 && /bound invalido/.test(error.message)
  );
  assert.throws(
    () => parseLicensesListQuery({ expiring_within_days: '400' }),
    (error) => error.status === 400 && /expiring_within_days/.test(error.message)
  );
});

test('parseAuditListQuery accepts filters', () => {
  const query = parseAuditListQuery({
    page: '1',
    limit: '30',
    event_type: 'license_renewed',
    result: 'success',
    search: 'admin@',
  });
  assert.equal(query.eventType, 'license_renewed');
  assert.equal(query.result, 'success');
  assert.equal(query.search, 'admin@');
});
