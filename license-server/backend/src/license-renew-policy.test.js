const test = require('node:test');
const assert = require('node:assert/strict');

const {
  computeRenewedExpiry,
  createLicenseRenewGuardError,
  parseRenewPayload,
} = require('./license-renew-policy');

test('parseRenewPayload accepts only 30/90/365', () => {
  assert.deepEqual(parseRenewPayload({ days: 30 }), { days: 30 });
  assert.deepEqual(parseRenewPayload({ days: '365' }), { days: 365 });
  assert.throws(() => parseRenewPayload({ days: 7 }), (e) => e.status === 400);
  assert.throws(() => parseRenewPayload({ days: 30, extra: 1 }), (e) => e.status === 400);
});

test('computeRenewedExpiry extends from future expiry', () => {
  assert.equal(
    computeRenewedExpiry('2027-01-01', 30, new Date('2026-08-08T12:00:00.000Z')),
    '2027-01-31'
  );
});

test('computeRenewedExpiry starts from today when already expired', () => {
  assert.equal(
    computeRenewedExpiry('2026-03-31', 365, new Date('2026-08-08T12:00:00.000Z')),
    '2027-08-08'
  );
});

test('createLicenseRenewGuardError blocks revoked licenses', () => {
  const error = createLicenseRenewGuardError({
    status: 'revoked',
    expiry: '2027-01-01',
  });
  assert.equal(error.status, 409);
  assert.equal(createLicenseRenewGuardError({ status: 'active', expiry: '2027-01-01' }), null);
});
