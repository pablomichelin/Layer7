function buildLockedScopesMetadata(guards = []) {
  return guards
    .filter((guard) => guard.lockedUntil)
    .map((guard) => ({
      scope_type: guard.scopeType,
      locked_until: guard.lockedUntil.toISOString(),
    }));
}

function buildAdminAccessDeniedAuditPayload({ req }) {
  return {
    component: 'auth',
    eventType: 'admin_access_denied',
    req,
    result: 'denied',
    reason: 'invalid_or_expired_session',
  };
}

function buildSessionValidationErrorAuditPayload({ req }) {
  return {
    component: 'auth',
    eventType: 'session_validation_error',
    req,
    result: 'error',
    reason: 'session_validation_exception',
  };
}

function buildLoginFailureAuditMetadata({ guards = [], adminId = null } = {}) {
  const metadata = {
    lockout_scopes: buildLockedScopesMetadata(guards),
  };

  if (adminId !== null && adminId !== undefined) {
    metadata.admin_id = adminId;
  }

  return metadata;
}

function buildLoginRejectedAuditPayload({
  email = null,
  req,
  reason,
}) {
  return {
    component: 'auth',
    eventType: 'login_rejected',
    actorIdentifier: email,
    req,
    result: 'denied',
    reason,
  };
}

function buildLoginLockedAuditPayload({
  email,
  req,
  activeLock,
}) {
  return {
    component: 'auth',
    eventType: 'login_locked',
    actorIdentifier: email,
    req,
    result: 'blocked',
    reason: `${activeLock.scopeType}_lockout_active`,
    metadata: { locked_until: activeLock.lockedUntil.toISOString() },
  };
}

function buildLoginFailedAuditPayload({
  email,
  req,
  guards = [],
  adminId = null,
}) {
  return {
    component: 'auth',
    eventType: 'login_failed',
    actorIdentifier: email,
    req,
    result: 'denied',
    reason: 'invalid_credentials',
    metadata: buildLoginFailureAuditMetadata({ guards, adminId }),
  };
}

function buildLoginErrorAuditPayload({
  email = null,
  req,
}) {
  return {
    component: 'auth',
    eventType: 'login_error',
    actorIdentifier: email,
    req,
    result: 'error',
    reason: 'login_exception',
  };
}

function buildLoginSucceededAuditPayload({
  admin,
  req,
}) {
  return {
    component: 'auth',
    eventType: 'login_succeeded',
    adminId: admin.id,
    actorIdentifier: admin.email,
    req,
    result: 'success',
    reason: 'credentials_validated',
  };
}

function buildSessionCreatedAuditPayload({
  admin,
  req,
  session,
}) {
  return {
    component: 'auth',
    eventType: 'session_created',
    adminId: admin.id,
    actorIdentifier: admin.email,
    req,
    result: 'success',
    reason: 'admin_session_issued',
    metadata: { session_id: session.metadata.session.id },
  };
}

function buildLogoutAuditPayload({ req, session, token }) {
  return {
    component: 'auth',
    eventType: 'session_revoked',
    adminId: session?.metadata.admin.id || null,
    actorIdentifier: session?.metadata.admin.email || null,
    req,
    result: 'success',
    reason: token ? 'logout' : 'logout_without_session',
    metadata: session ? { session_id: session.metadata.session.id } : null,
  };
}

function buildLogoutErrorAuditPayload({ req }) {
  return {
    component: 'auth',
    eventType: 'logout_error',
    req,
    result: 'error',
    reason: 'logout_exception',
  };
}

module.exports = {
  buildAdminAccessDeniedAuditPayload,
  buildLoginErrorAuditPayload,
  buildLoginFailedAuditPayload,
  buildLoginSucceededAuditPayload,
  buildLockedScopesMetadata,
  buildLoginFailureAuditMetadata,
  buildLoginLockedAuditPayload,
  buildLoginRejectedAuditPayload,
  buildLogoutErrorAuditPayload,
  buildLogoutAuditPayload,
  buildSessionValidationErrorAuditPayload,
  buildSessionCreatedAuditPayload,
};
