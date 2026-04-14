const test = require('node:test');
const assert = require('node:assert/strict');

const {
  createLicenseUpdateGuardError,
  listChangedLicenseFields,
} = require('./license-update-policy');

const HARDWARE_ID = 'a'.repeat(64);

test('listChangedLicenseFields detects changed mutable fields', () => {
  const changedFields = listChangedLicenseFields(
    {
      customer_id: 1,
      expiry: '2033-10-24',
      features: 'full',
      notes: 'old',
    },
    {
      customerId: 2,
      expiry: '2034-01-01',
      features: 'reports',
      notes: 'new',
    }
  );

  assert.deepEqual(changedFields, ['customer_id', 'expiry', 'features', 'notes']);
});

test('listChangedLicenseFields treats missing features as full', () => {
  const changedFields = listChangedLicenseFields(
    {
      customer_id: 1,
      expiry: '2033-10-24',
      features: null,
      notes: null,
    },
    {
      features: 'full',
    }
  );

  assert.deepEqual(changedFields, []);
});

test('createLicenseUpdateGuardError blocks customer changes on bound licenses', () => {
  const error = createLicenseUpdateGuardError(
    {
      id: 7,
      status: 'active',
      expiry: '2033-10-24',
      hardware_id: HARDWARE_ID,
      activated_at: null,
    },
    ['customer_id']
  );

  assert.equal(error.status, 409);
  assert.equal(
    error.message,
    'Licenca activada/bindada nao permite mudar customer_id. Crie nova licenca para outro cliente.'
  );
});

test('createLicenseUpdateGuardError blocks customer changes on activated licenses without hardware_id', () => {
  const error = createLicenseUpdateGuardError(
    {
      id: 8,
      status: 'active',
      expiry: '2033-10-24',
      hardware_id: null,
      activated_at: '2026-04-14T12:00:00.000Z',
    },
    ['customer_id']
  );

  assert.equal(error.status, 409);
});

test('createLicenseUpdateGuardError allows customer changes on unbound licenses', () => {
  const error = createLicenseUpdateGuardError(
    {
      id: 9,
      status: 'active',
      expiry: '2033-10-24',
      hardware_id: null,
      activated_at: null,
    },
    ['customer_id']
  );

  assert.equal(error, null);
});

test('createLicenseUpdateGuardError allows non-customer changes on bound licenses', () => {
  const error = createLicenseUpdateGuardError(
    {
      id: 10,
      status: 'active',
      expiry: '2033-10-24',
      hardware_id: HARDWARE_ID,
      activated_at: '2026-04-14T12:00:00.000Z',
    },
    ['expiry', 'features', 'notes']
  );

  assert.equal(error, null);
});
