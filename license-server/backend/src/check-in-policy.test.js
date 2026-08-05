const test = require('node:test');
const assert = require('node:assert/strict');

const {
  buildActiveCheckInResponse,
  buildDeniedCheckInResponse,
  formatExpiryDate,
  mapActivationErrorToDeniedResponse,
} = require('./check-in-policy');
const { createHttpError } = require('./http-error');

test('buildActiveCheckInResponse returns ADR-0021 active payload', () => {
  const response = buildActiveCheckInResponse(
    {
      expiry: '2027-12-31',
      customer_name: 'Cliente Teste',
    },
    {
      checkInIntervalHours: 168,
      maxOfflineHours: 336,
    }
  );

  assert.deepEqual(response, {
    status: 'active',
    expiry: '2027-12-31',
    customer: 'Cliente Teste',
    features: 'base',
    check_in_interval_hours: 168,
    max_offline_hours: 336,
  });
});

test('buildActiveCheckInResponse includes normalized SKU features', () => {
  const response = buildActiveCheckInResponse(
    {
      expiry: '2027-12-31',
      customer_name: 'Cliente Y',
      features: 'full,identity',
    },
    {
      checkInIntervalHours: 24,
      maxOfflineHours: 48,
    }
  );

  assert.equal(response.features, 'base,identity');
});

test('buildDeniedCheckInResponse returns revoked payload', () => {
  const response = buildDeniedCheckInResponse('revoked', 'Licenca revogada.');

  assert.deepEqual(response, {
    status: 'revoked',
    error: 'Licenca revogada.',
  });
});

test('mapActivationErrorToDeniedResponse maps revoked and expired errors', () => {
  const revoked = mapActivationErrorToDeniedResponse(
    createHttpError(409, 'Licenca revogada.')
  );
  const expired = mapActivationErrorToDeniedResponse(
    createHttpError(409, 'Licenca expirada.')
  );

  assert.equal(revoked.status, 'revoked');
  assert.equal(expired.status, 'expired');
  assert.equal(
    mapActivationErrorToDeniedResponse(createHttpError(409, 'Hardware ID nao corresponde.')),
    null
  );
});

test('formatExpiryDate normalizes Date and string values', () => {
  assert.equal(formatExpiryDate(new Date('2027-12-31T10:00:00.000Z')), '2027-12-31');
  assert.equal(formatExpiryDate('2027-12-31'), '2027-12-31');
  assert.equal(formatExpiryDate(null), null);
});
