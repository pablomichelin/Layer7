import test from 'node:test';
import assert from 'node:assert/strict';
import { AUTH_INVALID_EVENT } from './auth-events.js';
import { subscribeToInvalidAuthSession } from './auth-invalid-listener.js';

function createEventTargetDouble() {
  const listeners = new Map();

  return {
    addEventListener(type, handler) {
      const entries = listeners.get(type) || [];
      entries.push(handler);
      listeners.set(type, entries);
    },
    removeEventListener(type, handler) {
      const entries = listeners.get(type) || [];
      listeners.set(
        type,
        entries.filter((entry) => entry !== handler)
      );
    },
    dispatch(type) {
      const entries = listeners.get(type) || [];
      entries.forEach((handler) => handler());
    },
    count(type) {
      return (listeners.get(type) || []).length;
    },
  };
}

test('subscribeToInvalidAuthSession clears auth state when active', () => {
  const target = createEventTargetDouble();
  let clearCalls = 0;

  const unsubscribe = subscribeToInvalidAuthSession({
    clearAuthState() {
      clearCalls += 1;
    },
    isActive() {
      return true;
    },
    target,
  });

  assert.equal(target.count(AUTH_INVALID_EVENT), 1);
  target.dispatch(AUTH_INVALID_EVENT);
  assert.equal(clearCalls, 1);

  unsubscribe();
  assert.equal(target.count(AUTH_INVALID_EVENT), 0);
});

test('subscribeToInvalidAuthSession ignores invalid-session events when inactive', () => {
  const target = createEventTargetDouble();
  let clearCalls = 0;

  subscribeToInvalidAuthSession({
    clearAuthState() {
      clearCalls += 1;
    },
    isActive() {
      return false;
    },
    target,
  });

  target.dispatch(AUTH_INVALID_EVENT);
  assert.equal(clearCalls, 0);
});

test('subscribeToInvalidAuthSession becomes a no-op without an event target', () => {
  let clearCalls = 0;

  const unsubscribe = subscribeToInvalidAuthSession({
    clearAuthState() {
      clearCalls += 1;
    },
    isActive() {
      return true;
    },
    target: null,
  });

  unsubscribe();
  assert.equal(clearCalls, 0);
});
