const { clearSessionCookie, resolveSession } = require('./session');
const {
  ADMIN_AUTH_REQUIRED_MESSAGE,
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
} = require('./admin-surface');

async function authMiddleware(req, res, next) {
  try {
    const session = await resolveSession(req, res);
    if (!session) {
      clearSessionCookie(res);
      await auditAdminEvent({
        component: 'auth',
        eventType: 'admin_access_denied',
        req,
        result: 'denied',
        reason: 'invalid_or_expired_session',
      });
      return res.status(401).json({ error: ADMIN_AUTH_REQUIRED_MESSAGE });
    }

    req.admin = session.metadata.admin;
    req.adminSession = session.metadata.session;
    next();
  } catch (err) {
    console.error('[AUTH] Session validation error:', err.message);
    clearSessionCookie(res);
    await auditAdminEvent({
      component: 'auth',
      eventType: 'session_validation_error',
      req,
      result: 'error',
      reason: 'session_validation_exception',
    });
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
}

module.exports = authMiddleware;
