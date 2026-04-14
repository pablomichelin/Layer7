import test from 'node:test';
import assert from 'node:assert/strict';
import { getAuthGateState } from './auth-gate.js';

test('getAuthGateState prioritizes loading before auth decisions', () => {
  assert.equal(
    getAuthGateState({
      loading: true,
      isAuthenticated: true,
    }),
    'loading'
  );

  assert.equal(
    getAuthGateState({
      loading: true,
      isAuthenticated: false,
    }),
    'loading'
  );
});

test('getAuthGateState distinguishes authenticated and anonymous states', () => {
  assert.equal(
    getAuthGateState({
      loading: false,
      isAuthenticated: true,
    }),
    'authenticated'
  );

  assert.equal(
    getAuthGateState({
      loading: false,
      isAuthenticated: false,
    }),
    'anonymous'
  );
});
