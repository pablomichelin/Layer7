const test = require('node:test');
const assert = require('node:assert/strict');

const {
  normalizeStoredHardwareId,
  parseActivatePayload,
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
