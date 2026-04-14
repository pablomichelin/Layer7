const test = require('node:test');
const assert = require('node:assert/strict');

const {
  createActivationStateError,
  createHardwareBindingError,
  getBoundHardwareId,
} = require('./activation-policy');

const HARDWARE_A = 'a'.repeat(64);
const HARDWARE_B = 'b'.repeat(64);

test('createActivationStateError returns 409 for revoked licenses', () => {
  const error = createActivationStateError(
    { id: 42 },
    { revoked: true, effectiveStatus: 'revoked' }
  );

  assert.equal(error.status, 409);
  assert.equal(error.message, 'Licenca revogada.');
  assert.equal(error.licenseId, 42);
});

test('createActivationStateError returns 409 for expired licenses', () => {
  const error = createActivationStateError(
    { id: 43 },
    { revoked: false, effectiveStatus: 'expired' }
  );

  assert.equal(error.status, 409);
  assert.equal(error.message, 'Licenca expirada.');
  assert.equal(error.licenseId, 43);
});

test('createActivationStateError allows active licenses', () => {
  const error = createActivationStateError(
    { id: 44 },
    { revoked: false, effectiveStatus: 'active' }
  );

  assert.equal(error, null);
});

test('createHardwareBindingError returns 409 for hardware mismatch', () => {
  const error = createHardwareBindingError(
    { id: 45, hardware_id: HARDWARE_A },
    HARDWARE_B
  );

  assert.equal(error.status, 409);
  assert.equal(error.message, 'Hardware ID nao corresponde.');
  assert.equal(error.licenseId, 45);
});

test('createHardwareBindingError allows matching hardware', () => {
  const error = createHardwareBindingError(
    { id: 46, hardware_id: HARDWARE_A },
    HARDWARE_A
  );

  assert.equal(error, null);
});

test('createHardwareBindingError allows unbound licenses', () => {
  const error = createHardwareBindingError(
    { id: 47, hardware_id: null },
    HARDWARE_A
  );

  assert.equal(error, null);
});

test('getBoundHardwareId normalizes stored hardware IDs', () => {
  const hardwareId = getBoundHardwareId({ hardware_id: ` ${HARDWARE_A.toUpperCase()} ` });

  assert.equal(hardwareId, HARDWARE_A);
});
