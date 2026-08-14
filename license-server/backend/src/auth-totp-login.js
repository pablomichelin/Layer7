const {
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
  ADMIN_AUTH_LOCKED_MESSAGE,
} = require('./admin-surface');
const { buildAuthErrorResponse } = require('./auth-route-response');

function isAdminAccountDisabled(admin) {
  return Boolean(admin) && admin.is_active === false;
}

function passwordLoginRequiresTotp(admin) {
  return Boolean(admin?.totp_enabled && admin?.totp_secret);
}

function shouldResetLoginProtectionAfterPassword(admin) {
  return !passwordLoginRequiresTotp(admin);
}

function decideSecondFactorAttempt({
  challenge = null,
  admin = null,
  totpValid = false,
} = {}) {
  if (!challenge || !admin) {
    return {
      kind: 'invalid_second_factor',
      email: admin?.email || null,
      adminId: admin?.id || null,
      reason: 'challenge_or_admin_unresolved',
    };
  }

  if (isAdminAccountDisabled(admin)) {
    return {
      kind: 'invalid_second_factor',
      email: admin.email,
      adminId: admin.id,
      reason: 'account_disabled',
    };
  }

  if (!admin.totp_enabled || !admin.totp_secret || !totpValid) {
    return {
      kind: 'invalid_second_factor',
      email: admin.email,
      adminId: admin.id,
      reason: 'totp_invalid',
    };
  }

  return {
    kind: 'success',
    admin,
    email: admin.email,
    adminId: admin.id,
  };
}

function secondFactorHttpResponse(outcome) {
  if (outcome?.kind === 'locked') {
    return {
      status: 429,
      body: buildAuthErrorResponse(ADMIN_AUTH_LOCKED_MESSAGE),
    };
  }

  if (outcome?.kind === 'success') {
    return { status: 200, body: null };
  }

  return {
    status: 401,
    body: buildAuthErrorResponse(ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE),
  };
}

function lockedSecondFactorOutcome({ email = null, adminId = null, activeLock }) {
  return {
    kind: 'locked',
    email,
    adminId,
    activeLock,
  };
}

async function applySecondFactorProtection(outcome, {
  req,
  registerLoginFailure,
  resetLoginProtection,
}) {
  if (outcome.kind === 'success') {
    await resetLoginProtection({ email: outcome.email, req });
    return { reset: true, registeredFailure: false, guards: [] };
  }

  if (outcome.kind === 'locked') {
    return { reset: false, registeredFailure: false, guards: [] };
  }

  const guards = await registerLoginFailure({ email: outcome.email, req });
  return { reset: false, registeredFailure: true, guards };
}

module.exports = {
  applySecondFactorProtection,
  decideSecondFactorAttempt,
  isAdminAccountDisabled,
  lockedSecondFactorOutcome,
  passwordLoginRequiresTotp,
  secondFactorHttpResponse,
  shouldResetLoginProtectionAfterPassword,
};
