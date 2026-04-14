import test from 'node:test';
import assert from 'node:assert/strict';
import { getAdminAuthRequestOptions } from './auth-request-options.js';

test('getAdminAuthRequestOptions returns the canonical auth request flags', () => {
  assert.deepEqual(
    getAdminAuthRequestOptions(),
    { skipAuthRedirect: true }
  );
});
