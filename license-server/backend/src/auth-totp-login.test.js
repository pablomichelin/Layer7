const test = require('node:test');
const assert = require('node:assert/strict');
const {
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
  ADMIN_AUTH_LOCKED_MESSAGE,
} = require('./admin-surface');
const {
  applySecondFactorProtection,
  decideSecondFactorAttempt,
  isAdminAccountDisabled,
  lockedSecondFactorOutcome,
  passwordLoginRequiresTotp,
  secondFactorHttpResponse,
  shouldResetLoginProtectionAfterPassword,
} = require('./auth-totp-login');

const ACTIVE_ADMIN = {
  id: 7,
  email: 'owner@systemup.inf.br',
  is_active: true,
  totp_enabled: true,
  totp_secret: 'JBSWY3DPEHPK3PXP',
};

const DISABLED_ADMIN = {
  ...ACTIVE_ADMIN,
  is_active: false,
};

const CHALLENGE = {
  admin_id: 7,
  exp: Date.now() + 60_000,
  jti: 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
};

function genericUnauthorized() {
  return {
    status: 401,
    body: { error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE },
  };
}

test('is_active usa a mesma semântica === false do login/sessão', () => {
  assert.equal(isAdminAccountDisabled({ is_active: false }), true);
  assert.equal(isAdminAccountDisabled({ is_active: true }), false);
  assert.equal(isAdminAccountDisabled({ is_active: null }), false);
  assert.equal(isAdminAccountDisabled(undefined), false);
});

test('password OK com TOTP ligado não autoriza reset da protecção', () => {
  assert.equal(passwordLoginRequiresTotp(ACTIVE_ADMIN), true);
  assert.equal(shouldResetLoginProtectionAfterPassword(ACTIVE_ADMIN), false);
  assert.equal(shouldResetLoginProtectionAfterPassword({
    totp_enabled: false,
    totp_secret: null,
  }), true);
  assert.equal(shouldResetLoginProtectionAfterPassword({
    totp_enabled: true,
    totp_secret: null,
  }), true);
});

test('P1-3 — conta desactivada recusa mesmo com TOTP válido', () => {
  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: DISABLED_ADMIN,
    totpValid: true,
  });

  assert.equal(outcome.kind, 'invalid_second_factor');
  assert.equal(outcome.reason, 'account_disabled');
  assert.deepEqual(secondFactorHttpResponse(outcome), genericUnauthorized());
});

test('P0-2 — desafio HMAC sem jti não autoriza o segundo factor', () => {
  const outcome = decideSecondFactorAttempt({
    challenge: { admin_id: 7, exp: Date.now() + 60_000 },
    admin: ACTIVE_ADMIN,
    totpValid: true,
  });

  assert.equal(outcome.kind, 'invalid_second_factor');
  assert.equal(outcome.reason, 'challenge_or_admin_unresolved');
  assert.deepEqual(secondFactorHttpResponse(outcome), genericUnauthorized());
});

test('P1-3 — falha TOTP, desafio inválido e conta desactivada não enumeram', () => {
  const disabled = secondFactorHttpResponse(decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: DISABLED_ADMIN,
    totpValid: true,
  }));
  const badCode = secondFactorHttpResponse(decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: ACTIVE_ADMIN,
    totpValid: false,
  }));
  const missingAdmin = secondFactorHttpResponse(decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: null,
    totpValid: false,
  }));
  const badChallenge = secondFactorHttpResponse(decideSecondFactorAttempt({
    challenge: null,
    admin: null,
    totpValid: false,
  }));

  assert.deepEqual(disabled, genericUnauthorized());
  assert.deepEqual(badCode, genericUnauthorized());
  assert.deepEqual(missingAdmin, genericUnauthorized());
  assert.deepEqual(badChallenge, genericUnauthorized());
});

test('sucesso do segundo factor mantém o contrato HTTP para a rota emitir a sessão', () => {
  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: ACTIVE_ADMIN,
    totpValid: true,
  });

  assert.equal(outcome.kind, 'success');
  assert.equal(outcome.admin, ACTIVE_ADMIN);
  assert.deepEqual(secondFactorHttpResponse(outcome), { status: 200, body: null });
});

test('lock activo no segundo factor devolve o mesmo 429 do login', () => {
  const activeLock = {
    scopeType: 'account',
    scopeKey: ACTIVE_ADMIN.email,
    lockedUntil: new Date('2026-08-14T16:00:00.000Z'),
  };
  const outcome = lockedSecondFactorOutcome({
    email: ACTIVE_ADMIN.email,
    adminId: ACTIVE_ADMIN.id,
    activeLock,
  });

  assert.deepEqual(secondFactorHttpResponse(outcome), {
    status: 429,
    body: { error: ADMIN_AUTH_LOCKED_MESSAGE },
  });
});

test('conta desactivada incrementa a guarda e não faz reset', async () => {
  const calls = { reset: 0, failures: [] };
  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: DISABLED_ADMIN,
    totpValid: true,
  });
  const protection = await applySecondFactorProtection(outcome, {
    req: { ip: '203.0.113.10' },
    registerLoginFailure: async ({ email, req }) => {
      calls.failures.push({ email, ip: req.ip });
      return [{ scopeType: 'account', scopeKey: email, failureCount: 1, lockedUntil: null }];
    },
    resetLoginProtection: async () => {
      calls.reset += 1;
    },
  });

  assert.equal(protection.reset, false);
  assert.equal(protection.registeredFailure, true);
  assert.deepEqual(calls.failures, [{ email: DISABLED_ADMIN.email, ip: '203.0.113.10' }]);
  assert.equal(calls.reset, 0);
});

test('falha TOTP participa da protecção existente sem reset', async () => {
  const calls = { reset: 0, failures: [] };
  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: ACTIVE_ADMIN,
    totpValid: false,
  });
  const protection = await applySecondFactorProtection(outcome, {
    req: { ip: '203.0.113.11' },
    registerLoginFailure: async ({ email }) => {
      calls.failures.push(email);
      return [{
        scopeType: 'account',
        scopeKey: email,
        failureCount: 4,
        lockedUntil: null,
      }];
    },
    resetLoginProtection: async () => {
      calls.reset += 1;
    },
  });

  assert.equal(outcome.reason, 'totp_invalid');
  assert.equal(protection.reset, false);
  assert.equal(protection.registeredFailure, true);
  assert.deepEqual(calls.failures, [ACTIVE_ADMIN.email]);
  assert.equal(calls.reset, 0);
  assert.equal(protection.guards[0].failureCount, 4);
});

test('quase locked + password OK sem reset + falha TOTP chega ao lock', async () => {
  assert.equal(shouldResetLoginProtectionAfterPassword(ACTIVE_ADMIN), false);

  let failureCount = 4;
  let lockedUntil = null;
  const req = { ip: '203.0.113.12' };

  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: ACTIVE_ADMIN,
    totpValid: false,
  });
  const protection = await applySecondFactorProtection(outcome, {
    req,
    registerLoginFailure: async ({ email }) => {
      failureCount += 1;
      lockedUntil = failureCount >= 5
        ? new Date('2026-08-14T16:15:00.000Z')
        : null;
      return [{
        scopeType: 'account',
        scopeKey: email,
        failureCount,
        lockedUntil,
      }];
    },
    resetLoginProtection: async () => {
      throw new Error('resetLoginProtection não pode correr antes do TOTP válido');
    },
  });

  assert.equal(protection.reset, false);
  assert.equal(failureCount, 5);
  assert.ok(lockedUntil);

  const locked = lockedSecondFactorOutcome({
    email: ACTIVE_ADMIN.email,
    adminId: ACTIVE_ADMIN.id,
    activeLock: {
      scopeType: 'account',
      scopeKey: ACTIVE_ADMIN.email,
      lockedUntil,
    },
  });
  assert.equal(secondFactorHttpResponse(locked).status, 429);
});

test('sucesso TOTP é o único caminho que faz reset da protecção', async () => {
  const calls = { reset: [], failures: 0 };
  const outcome = decideSecondFactorAttempt({
    challenge: CHALLENGE,
    admin: ACTIVE_ADMIN,
    totpValid: true,
  });
  const protection = await applySecondFactorProtection(outcome, {
    req: { ip: '203.0.113.13' },
    registerLoginFailure: async () => {
      calls.failures += 1;
      return [];
    },
    resetLoginProtection: async ({ email, req }) => {
      calls.reset.push({ email, ip: req.ip });
    },
  });

  assert.equal(protection.reset, true);
  assert.equal(protection.registeredFailure, false);
  assert.equal(calls.failures, 0);
  assert.deepEqual(calls.reset, [{ email: ACTIVE_ADMIN.email, ip: '203.0.113.13' }]);
});

test('lock activo não incrementa nem faz reset', async () => {
  const calls = { reset: 0, failures: 0 };
  const outcome = lockedSecondFactorOutcome({
    email: ACTIVE_ADMIN.email,
    adminId: ACTIVE_ADMIN.id,
    activeLock: {
      scopeType: 'ip',
      scopeKey: '203.0.113.14',
      lockedUntil: new Date('2026-08-14T16:20:00.000Z'),
    },
  });
  const protection = await applySecondFactorProtection(outcome, {
    req: { ip: '203.0.113.14' },
    registerLoginFailure: async () => {
      calls.failures += 1;
      return [];
    },
    resetLoginProtection: async () => {
      calls.reset += 1;
    },
  });

  assert.equal(protection.reset, false);
  assert.equal(protection.registeredFailure, false);
  assert.equal(calls.failures, 0);
  assert.equal(calls.reset, 0);
});
