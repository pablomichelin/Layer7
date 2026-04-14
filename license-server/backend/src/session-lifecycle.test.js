const test = require('node:test');
const assert = require('node:assert/strict');
const { getSessionLifecycleDecision } = require('./session-lifecycle');

test('getSessionLifecycleDecision marks the session as expired by idle timeout', () => {
  const now = new Date('2026-04-14T12:00:00.000Z');

  assert.deepEqual(
    getSessionLifecycleDecision({
      now,
      expiresAt: new Date('2026-04-14T11:59:59.000Z'),
      absoluteExpiresAt: new Date('2026-04-14T20:00:00.000Z'),
      renewWindowMs: 5 * 60 * 1000,
      idleTimeoutMs: 30 * 60 * 1000,
    }),
    { action: 'expired' }
  );
});

test('getSessionLifecycleDecision marks the session as expired by absolute timeout', () => {
  const now = new Date('2026-04-14T12:00:00.000Z');

  assert.deepEqual(
    getSessionLifecycleDecision({
      now,
      expiresAt: new Date('2026-04-14T12:10:00.000Z'),
      absoluteExpiresAt: new Date('2026-04-14T12:00:00.000Z'),
      renewWindowMs: 5 * 60 * 1000,
      idleTimeoutMs: 30 * 60 * 1000,
    }),
    { action: 'expired' }
  );
});

test('getSessionLifecycleDecision keeps the session alive outside the renew window', () => {
  const now = new Date('2026-04-14T12:00:00.000Z');
  const expiresAt = new Date('2026-04-14T12:10:00.000Z');

  assert.deepEqual(
    getSessionLifecycleDecision({
      now,
      expiresAt,
      absoluteExpiresAt: new Date('2026-04-14T20:00:00.000Z'),
      renewWindowMs: 5 * 60 * 1000,
      idleTimeoutMs: 30 * 60 * 1000,
    }),
    {
      action: 'keep_alive',
      nextExpiresAt: expiresAt,
    }
  );
});

test('getSessionLifecycleDecision renews the session inside the renew window', () => {
  const now = new Date('2026-04-14T12:00:00.000Z');

  assert.deepEqual(
    getSessionLifecycleDecision({
      now,
      expiresAt: new Date('2026-04-14T12:03:00.000Z'),
      absoluteExpiresAt: new Date('2026-04-14T20:00:00.000Z'),
      renewWindowMs: 5 * 60 * 1000,
      idleTimeoutMs: 30 * 60 * 1000,
    }),
    {
      action: 'renew',
      nextExpiresAt: new Date('2026-04-14T12:30:00.000Z'),
    }
  );
});

test('getSessionLifecycleDecision caps renewals at the absolute timeout', () => {
  const now = new Date('2026-04-14T12:00:00.000Z');

  assert.deepEqual(
    getSessionLifecycleDecision({
      now,
      expiresAt: new Date('2026-04-14T12:03:00.000Z'),
      absoluteExpiresAt: new Date('2026-04-14T12:20:00.000Z'),
      renewWindowMs: 5 * 60 * 1000,
      idleTimeoutMs: 30 * 60 * 1000,
    }),
    {
      action: 'renew',
      nextExpiresAt: new Date('2026-04-14T12:20:00.000Z'),
    }
  );
});
