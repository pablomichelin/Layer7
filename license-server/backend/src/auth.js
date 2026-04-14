const { clearSessionCookie, resolveSession } = require('./session');
const { createAuthMiddleware } = require('./auth-middleware');
const {
  ADMIN_AUTH_REQUIRED_MESSAGE,
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
} = require('./admin-surface');

module.exports = createAuthMiddleware({
  clearSessionCookie,
  resolveSession,
  auditAdminEvent,
  authRequiredMessage: ADMIN_AUTH_REQUIRED_MESSAGE,
  internalErrorMessage: ADMIN_INTERNAL_ERROR_MESSAGE,
});
