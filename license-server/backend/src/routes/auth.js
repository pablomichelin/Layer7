const { Router } = require('express');
const bcrypt = require('bcryptjs');
const pool = require('../db');
const auth = require('../auth');
const { buildAdminAuthResponse } = require('../auth-response');
const {
  buildAuthErrorResponse,
  buildLogoutSuccessResponse,
} = require('../auth-route-response');
const {
  buildLoginErrorAuditPayload,
  buildLoginFailedAuditPayload,
  buildLoginLockedAuditPayload,
  buildLoginRejectedAuditPayload,
  buildLoginSucceededAuditPayload,
  buildLogoutErrorAuditPayload,
  buildLogoutAuditPayload,
  buildSessionCreatedAuditPayload,
} = require('../auth-route-helpers');
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
  createBearerSessionToken,
  createSession,
  getAdminAccessTokenFromRequest,
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
      await auditAdminEvent(buildLoginRejectedAuditPayload({
        email: email || null,
        req,
        reason: 'missing_credentials',
      }));
      return res.status(400).json(buildAuthErrorResponse('Email e password obrigatorios'));
    }

    if (!requireSecureSessionRequest(req)) {
      clearSessionCookie(res);
      await auditAdminEvent(buildLoginRejectedAuditPayload({
        email,
        req,
        reason: 'insecure_channel',
      }));
      return res.status(400).json(buildAuthErrorResponse(ADMIN_AUTH_CHANNEL_MESSAGE));
    }

    const activeLock = await getActiveLoginLock({ email, req });
    if (activeLock) {
      await auditAdminEvent(buildLoginLockedAuditPayload({
        email,
        req,
        activeLock,
      }));
      return res.status(429).json(buildAuthErrorResponse(ADMIN_AUTH_LOCKED_MESSAGE));
    }

    const result = await pool.query('SELECT * FROM admins WHERE LOWER(email) = $1', [email]);
    if (result.rows.length === 0) {
      const guards = await registerLoginFailure({ email, req });
      await auditAdminEvent(buildLoginFailedAuditPayload({
        email,
        req,
        guards,
      }));
      return res.status(401).json(buildAuthErrorResponse(ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE));
    }

    const admin = result.rows[0];
    const valid = await bcrypt.compare(password, admin.password_hash);
    if (!valid) {
      const guards = await registerLoginFailure({ email, req });
      await auditAdminEvent(buildLoginFailedAuditPayload({
        email,
        req,
        guards,
        adminId: admin.id,
      }));
      return res.status(401).json(buildAuthErrorResponse(ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE));
    }

    await resetLoginProtection({ email, req });

    const session = await createSession(admin, req);
    setSessionCookie(res, session.token, session.metadata.session.expires_at);

    await auditAdminEvent(buildLoginSucceededAuditPayload({ admin, req }));
    await auditAdminEvent(buildSessionCreatedAuditPayload({ admin, req, session }));

    res.json(buildAdminAuthResponse(
      {
        token: session.token,
        metadata: toSessionResponsePayload(session.metadata),
      },
      createBearerSessionToken
    ));
  } catch (err) {
    console.error('[AUTH] Login error:', err.message);
    await auditAdminEvent(buildLoginErrorAuditPayload({
      email: normalizeAdminEmail(req.body?.email),
      req,
    }));
    res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
  }
});

router.get('/session', auth, async (req, res) => {
  res.json(buildAdminAuthResponse(
    {
      token: req.adminToken,
      metadata: toSessionResponsePayload({
        admin: req.admin,
        session: req.adminSession,
      }),
    },
    createBearerSessionToken
  ));
});

router.post('/logout', async (req, res) => {
  try {
    const session = await resolveSession(req, res);
    const token = getAdminAccessTokenFromRequest(req);
    await revokeSessionByToken(token);
    clearSessionCookie(res);

    await auditAdminEvent(buildLogoutAuditPayload({ req, session, token }));

    res.json(buildLogoutSuccessResponse());
  } catch (err) {
    console.error('[AUTH] Logout error:', err.message);
    clearSessionCookie(res);
    await auditAdminEvent(buildLogoutErrorAuditPayload({ req }));
    res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
  }
});

module.exports = router;
