const test = require('node:test');
const assert = require('node:assert/strict');

const {
  createLicenseDownloadGuardError,
  getDownloadHardwareId,
} = require('./license-download-policy');

const HARDWARE_ID = 'a'.repeat(64);

test('getDownloadHardwareId normalizes valid hardware IDs', () => {
  assert.equal(getDownloadHardwareId({ hardware_id: ` ${HARDWARE_ID.toUpperCase()} ` }), HARDWARE_ID);
});

test('getDownloadHardwareId returns null for unbound licenses', () => {
  assert.equal(getDownloadHardwareId({ hardware_id: null }), null);
});

test('createLicenseDownloadGuardError blocks unactivated licenses', () => {
  const guard = createLicenseDownloadGuardError({
    id: 7,
    status: 'active',
    hardware_id: null,
  });

  assert.equal(guard.error.status, 409);
  assert.equal(guard.error.message, 'Licenca ainda nao foi activada.');
  assert.equal(guard.reason, 'license_not_activated');
  assert.deepEqual(guard.metadata, { license_id: 7 });
});

test('createLicenseDownloadGuardError blocks non-active licenses', () => {
  const guard = createLicenseDownloadGuardError({
    id: 8,
    status: 'revoked',
    hardware_id: HARDWARE_ID,
  });

  assert.equal(guard.error.status, 409);
  assert.equal(guard.error.message, 'Licenca nao esta em estado valido para download.');
  assert.equal(guard.reason, 'license_state_invalid_for_download');
  assert.deepEqual(guard.metadata, { license_id: 8, status: 'revoked' });
});

test('createLicenseDownloadGuardError allows active bound licenses', () => {
  const guard = createLicenseDownloadGuardError({
    id: 9,
    status: 'active',
    hardware_id: HARDWARE_ID,
  });

  assert.equal(guard, null);
});
