import test from 'node:test';
import assert from 'node:assert/strict';
import { summarizeCustomerLicenses } from './customer-license-summary.js';

test('summarizeCustomerLicenses counts status, bound and expiring window', () => {
  const now = new Date('2026-08-08T12:00:00.000Z');
  const summary = summarizeCustomerLicenses([
    { status: 'active', expiry: '2026-08-20', hardware_id: 'abc' },
    { status: 'active', expiry: '2027-01-01', hardware_id: null },
    { status: 'revoked', expiry: '2026-09-01', hardware_id: 'x' },
    { status: 'expired', expiry: '2025-01-01', hardware_id: '' },
  ], now);

  assert.deepEqual(summary, {
    total: 4,
    active: 2,
    revoked: 1,
    expired: 1,
    expiring30d: 1,
    bound: 2,
  });
});

test('summarizeCustomerLicenses tolerates empty input', () => {
  assert.deepEqual(summarizeCustomerLicenses(null), {
    total: 0,
    active: 0,
    revoked: 0,
    expired: 0,
    expiring30d: 0,
    bound: 0,
  });
});
