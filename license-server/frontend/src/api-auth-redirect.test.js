import test from 'node:test';
import assert from 'node:assert/strict';
import { handleInvalidAuthSession } from './api-auth-redirect.js';

test('handleInvalidAuthSession clears auth state, emits the event and redirects', () => {
  const calls = [];

  handleInvalidAuthSession({
    clearAuthToken() {
      calls.push('clear');
    },
    notifyInvalidSession() {
      calls.push('notify');
    },
    redirectToLogin() {
      calls.push('redirect');
    },
  });

  assert.deepEqual(calls, ['clear', 'notify', 'redirect']);
});
