import test from 'node:test';
import assert from 'node:assert/strict';
import {
  AUTH_LOGIN_PATH,
  AUTH_LOGOUT_PATH,
  AUTH_SESSION_PATH,
} from './auth-paths.js';

test('auth paths expose the canonical frontend auth endpoints', () => {
  assert.equal(AUTH_LOGIN_PATH, '/auth/login');
  assert.equal(AUTH_LOGOUT_PATH, '/auth/logout');
  assert.equal(AUTH_SESSION_PATH, '/auth/session');
});
