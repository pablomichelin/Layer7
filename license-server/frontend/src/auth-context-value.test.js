import test from 'node:test';
import assert from 'node:assert/strict';
import { buildAuthContextValue } from './auth-context-value.js';

test('buildAuthContextValue reports authenticated only when admin and session exist', () => {
  const login = () => {};
  const logout = () => {};
  const refreshSession = () => {};

  assert.equal(
    buildAuthContextValue({
      admin: { id: 7 },
      session: { id: 11 },
      loading: false,
      login,
      logout,
      refreshSession,
    }).isAuthenticated,
    true
  );

  assert.equal(
    buildAuthContextValue({
      admin: { id: 7 },
      session: null,
      loading: false,
      login,
      logout,
      refreshSession,
    }).isAuthenticated,
    false
  );

  assert.equal(
    buildAuthContextValue({
      admin: null,
      session: { id: 11 },
      loading: false,
      login,
      logout,
      refreshSession,
    }).isAuthenticated,
    false
  );
});

test('buildAuthContextValue preserves the provider contract fields', () => {
  const login = () => 'login';
  const logout = () => 'logout';
  const refreshSession = () => 'refresh';

  assert.deepEqual(
    buildAuthContextValue({
      admin: { id: 7, email: 'admin@example.com' },
      session: { id: 11 },
      loading: true,
      login,
      logout,
      refreshSession,
    }),
    {
      admin: { id: 7, email: 'admin@example.com' },
      session: { id: 11 },
      loading: true,
      isAuthenticated: true,
      login,
      logout,
      refreshSession,
    }
  );
});
