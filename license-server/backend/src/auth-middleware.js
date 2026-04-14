const {
  buildAdminAccessDeniedAuditPayload,
  buildSessionValidationErrorAuditPayload,
} = require('./auth-route-helpers');
const { buildAuthErrorResponse } = require('./auth-route-response');

function createAuthMiddleware({
  clearSessionCookie,
  resolveSession,
  auditAdminEvent,
  authRequiredMessage,
  internalErrorMessage,
  logError = (...args) => console.error(...args),
}) {
  return async function authMiddleware(req, res, next) {
    try {
      const session = await resolveSession(req, res);
      if (!session) {
        clearSessionCookie(res);
        await auditAdminEvent(buildAdminAccessDeniedAuditPayload({ req }));
        return res.status(401).json(buildAuthErrorResponse(authRequiredMessage));
      }

      req.admin = session.metadata.admin;
      req.adminSession = session.metadata.session;
      req.adminToken = session.token;
      next();
    } catch (err) {
      logError('[AUTH] Session validation error:', err.message);
      clearSessionCookie(res);
      await auditAdminEvent(buildSessionValidationErrorAuditPayload({ req }));
      return res.status(500).json(buildAuthErrorResponse(internalErrorMessage));
    }
  };
}

module.exports = {
  createAuthMiddleware,
};
