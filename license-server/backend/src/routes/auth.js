const { Router } = require('express');
const bcrypt = require('bcryptjs');
const pool = require('../db');
const auth = require('../auth');
const {
  ADMIN_AUTH_CHANNEL_MESSAGE,
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
  ADMIN_AUTH_LOCKED_MESSAGE,
  ADMIN_INTERNAL_ERROR_MESSAGE,
  auditAdminEvent,
  getActiveLoginLock,
  loginIdentityLimiter,
  loginIpLimiter,
  normalizeAdminEmail,
  registerLoginFailure,
  resetLoginProtection,
} = require('../admin-surface');
const {
  clearSessionCookie,
  createSession,
  getSessionTokenFromRequest,
  requireSecureSessionRequest,
  resolveSession,
  revokeSessionByToken,
  setSessionCookie,
  toSessionResponsePayload,
} = require('../session');

const router = Router();

router.post('/login', loginIpLimiter, loginIdentityLimiter, async (req, res) => {
  try {
    const email = normalizeAdminEmail(req.body?.email);
    const password = req.body?.password;

    if (!email || !password) {
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_rejected',
        actorIdentifier: email || null,
        req,
        result: 'denied',
        reason: 'missing_credentials',
      });
      return res.status(400).json({ error: 'Email e password obrigatorios' });
    }

    if (!requireSecureSessionRequest(req)) {
      clearSessionCookie(res);
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_rejected',
        actorIdentifier: email,
        req,
        result: 'denied',
        reason: 'insecure_channel',
      });
      return res.status(400).json({ error: ADMIN_AUTH_CHANNEL_MESSAGE });
    }

    const activeLock = await getActiveLoginLock({ email, req });
    if (activeLock) {
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_locked',
        actorIdentifier: email,
        req,
        result: 'blocked',
        reason: `${activeLock.scopeType}_lockout_active`,
        metadata: { locked_until: activeLock.lockedUntil.toISOString() },
      });
      return res.status(429).json({ error: ADMIN_AUTH_LOCKED_MESSAGE });
    }

    const result = await pool.query('SELECT * FROM admins WHERE LOWER(email) = $1', [email]);
    if (result.rows.length === 0) {
      const guards = await registerLoginFailure({ email, req });
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_failed',
        actorIdentifier: email,
        req,
        result: 'denied',
        reason: 'invalid_credentials',
        metadata: {
          lockout_scopes: guards
            .filter((guard) => guard.lockedUntil)
            .map((guard) => ({
              scope_type: guard.scopeType,
              locked_until: guard.lockedUntil.toISOString(),
            })),
        },
      });
      return res.status(401).json({ error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
    }

    const admin = result.rows[0];
    const valid = await bcrypt.compare(password, admin.password_hash);
    if (!valid) {
      const guards = await registerLoginFailure({ email, req });
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_failed',
        actorIdentifier: email,
        req,
        result: 'denied',
        reason: 'invalid_credentials',
        metadata: {
          admin_id: admin.id,
          lockout_scopes: guards
            .filter((guard) => guard.lockedUntil)
            .map((guard) => ({
              scope_type: guard.scopeType,
              locked_until: guard.lockedUntil.toISOString(),
            })),
        },
      });
      return res.status(401).json({ error: ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE });
    }

    await resetLoginProtection({ email, req });

    const session = await createSession(admin, req);
    setSessionCookie(res, session.token, session.metadata.session.expires_at);

    await auditAdminEvent({
      component: 'auth',
      eventType: 'login_succeeded',
      adminId: admin.id,
      actorIdentifier: admin.email,
      req,
      result: 'success',
      reason: 'credentials_validated',
    });
    await auditAdminEvent({
      component: 'auth',
      eventType: 'session_created',
      adminId: admin.id,
      actorIdentifier: admin.email,
      req,
      result: 'success',
      reason: 'admin_session_issued',
      metadata: { session_id: session.metadata.session.id },
    });

    res.json(toSessionResponsePayload(session.metadata));
  } catch (err) {
    console.error('[AUTH] Login error:', err.message);
    await auditAdminEvent({
      component: 'auth',
      eventType: 'login_error',
      actorIdentifier: normalizeAdminEmail(req.body?.email),
      req,
      result: 'error',
      reason: 'login_exception',
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/session', auth, async (req, res) => {
  res.json(toSessionResponsePayload({
    admin: req.admin,
    session: req.adminSession,
  }));
});

router.post('/logout', async (req, res) => {
  try {
    const session = await resolveSession(req, res);
    const token = getSessionTokenFromRequest(req);
    await revokeSessionByToken(token);
    clearSessionCookie(res);

    await auditAdminEvent({
      component: 'auth',
      eventType: 'session_revoked',
      adminId: session?.metadata.admin.id || null,
      actorIdentifier: session?.metadata.admin.email || null,
      req,
      result: 'success',
      reason: token ? 'logout' : 'logout_without_session',
      metadata: session ? { session_id: session.metadata.session.id } : null,
    });

    res.json({ message: 'Sessao encerrada' });
  } catch (err) {
    console.error('[AUTH] Logout error:', err.message);
    clearSessionCookie(res);
    await auditAdminEvent({
      component: 'auth',
      eventType: 'logout_error',
      req,
      result: 'error',
      reason: 'logout_exception',
    });
    res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
