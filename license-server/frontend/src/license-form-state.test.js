import test from 'node:test';
import assert from 'node:assert/strict';
import {
  buildLicenseFormState,
  buildLicenseSavePayload,
  isLicenseCustomerChangeBlocked,
  toLicenseDateInputValue,
} from './license-form-state.js';

test('toLicenseDateInputValue keeps only the ISO date portion', () => {
  assert.equal(toLicenseDateInputValue('2026-12-31T00:00:00.000Z'), '2026-12-31');
  assert.equal(toLicenseDateInputValue('2026-12-31'), '2026-12-31');
  assert.equal(toLicenseDateInputValue(null), '');
});

test('buildLicenseFormState maps backend license data to form fields', () => {
  assert.deepEqual(
    buildLicenseFormState({
      customer_id: 7,
      expiry: '2026-12-31T00:00:00.000Z',
      features: 'full,reports',
      notes: 'Contrato anual',
    }),
    {
      customer_id: '7',
      expiry: '2026-12-31',
      features: 'full,reports',
      notes: 'Contrato anual',
    }
  );
});

test('buildLicenseFormState uses safe defaults for optional license fields', () => {
  assert.deepEqual(
    buildLicenseFormState({}),
    {
      customer_id: '',
      expiry: '',
      features: 'full',
      notes: '',
    }
  );
});

test('buildLicenseSavePayload preserves the API payload expected by the backend', () => {
  assert.deepEqual(
    buildLicenseSavePayload({
      customer_id: '11',
      expiry: '2033-10-24',
      features: 'full',
      notes: '',
    }),
    {
      customer_id: 11,
      expiry: '2033-10-24',
      features: 'full',
      notes: '',
    }
  );
});

test('isLicenseCustomerChangeBlocked follows the backend binding guardrail', () => {
  assert.equal(isLicenseCustomerChangeBlocked({ isEdit: false, license: { hardware_id: 'abc' } }), false);
  assert.equal(isLicenseCustomerChangeBlocked({ isEdit: true, license: null }), false);
  assert.equal(isLicenseCustomerChangeBlocked({ isEdit: true, license: { hardware_id: '' } }), false);
  assert.equal(isLicenseCustomerChangeBlocked({ isEdit: true, license: { hardware_id: 'abc' } }), true);
  assert.equal(isLicenseCustomerChangeBlocked({ isEdit: true, license: { activated_at: '2026-04-14T00:00:00Z' } }), true);
});
