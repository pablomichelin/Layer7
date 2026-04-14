const test = require('node:test');
const assert = require('node:assert/strict');

const {
  applyEffectiveLicenseState,
  getEffectiveLicenseState,
  LICENSE_SQL_ACTIVE_CONDITION,
  LICENSE_SQL_EXPIRED_CONDITION,
  LICENSE_SQL_REVOKED_CONDITION,
} = require('./license-state');

const FUTURE_DATE = '2999-12-31';
const PAST_DATE = '2000-01-01';
const HARDWARE_ID = 'a'.repeat(64);

test('getEffectiveLicenseState treats active non-expired licenses as active', () => {
  const state = getEffectiveLicenseState({
    status: 'active',
    expiry: FUTURE_DATE,
    hardware_id: null,
  });

  assert.equal(state.persistedStatus, 'active');
  assert.equal(state.effectiveStatus, 'active');
  assert.equal(state.active, true);
  assert.equal(state.expiredByDate, false);
  assert.equal(state.expiredPersisted, false);
  assert.equal(state.revoked, false);
  assert.equal(state.activated, false);
  assert.equal(state.normalizedHardwareId, null);
});

test('getEffectiveLicenseState treats active licenses past expiry as expired', () => {
  const state = getEffectiveLicenseState({
    status: 'active',
    expiry: PAST_DATE,
    hardware_id: HARDWARE_ID,
  });

  assert.equal(state.effectiveStatus, 'expired');
  assert.equal(state.active, false);
  assert.equal(state.expiredByDate, true);
  assert.equal(state.expiredPersisted, false);
  assert.equal(state.revoked, false);
  assert.equal(state.activated, true);
  assert.equal(state.normalizedHardwareId, HARDWARE_ID);
});

test('getEffectiveLicenseState preserves persisted expired state before date expiry', () => {
  const state = getEffectiveLicenseState({
    status: 'expired',
    expiry: FUTURE_DATE,
    hardware_id: null,
  });

  assert.equal(state.effectiveStatus, 'expired');
  assert.equal(state.expiredByDate, false);
  assert.equal(state.expiredPersisted, true);
  assert.equal(state.revoked, false);
});

test('getEffectiveLicenseState gives revoked precedence over expiry', () => {
  const state = getEffectiveLicenseState({
    status: 'revoked',
    expiry: PAST_DATE,
    hardware_id: HARDWARE_ID.toUpperCase(),
  });

  assert.equal(state.effectiveStatus, 'revoked');
  assert.equal(state.active, false);
  assert.equal(state.expiredByDate, true);
  assert.equal(state.revoked, true);
  assert.equal(state.activated, true);
  assert.equal(state.normalizedHardwareId, HARDWARE_ID);
});

test('applyEffectiveLicenseState returns non-object values unchanged', () => {
  assert.equal(applyEffectiveLicenseState(null), null);
  assert.equal(applyEffectiveLicenseState(undefined), undefined);
  assert.equal(applyEffectiveLicenseState('license'), 'license');
});

test('applyEffectiveLicenseState overlays the effective status without mutating input', () => {
  const license = {
    id: 5,
    status: 'active',
    expiry: PAST_DATE,
  };

  const projected = applyEffectiveLicenseState(license);

  assert.equal(projected.status, 'expired');
  assert.equal(projected.id, 5);
  assert.equal(license.status, 'active');
  assert.notEqual(projected, license);
});

test('SQL state predicates stay aligned with the effective state contract', () => {
  assert.equal(LICENSE_SQL_ACTIVE_CONDITION, "status = 'active' AND expiry >= CURRENT_DATE");
  assert.equal(
    LICENSE_SQL_EXPIRED_CONDITION,
    "(status = 'expired' OR (status = 'active' AND expiry < CURRENT_DATE))"
  );
  assert.equal(LICENSE_SQL_REVOKED_CONDITION, "status = 'revoked'");
});
