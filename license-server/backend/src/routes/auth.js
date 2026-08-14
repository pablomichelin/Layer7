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
  applySecondFactorProtection,
  decideSecondFactorAttempt,
  lockedSecondFactorOutcome,
  passwordLoginRequiresTotp,
  secondFactorHttpResponse,
  shouldResetLoginProtectionAfterPassword,
} = require('../auth-totp-login');
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
const {
  buildOtpauthUri,
  createTotpChallengeToken,
  generateTotpSecret,
  parseTotpChallengeToken,
  verifyTotp,
} = require('../totp');
const { requirePermission } = require('../require-permission');
const { getTotpHmacSecret } = require('../admin-bearer-secret');

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
    if (admin.is_active === false) {
      await auditAdminEvent(buildLoginRejectedAuditPayload({
        email,
        req,
        reason: 'account_disabled',
      }));
      return res.status(403).json(buildAuthErrorResponse('Conta desactivada.'));
    }

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

    if (passwordLoginRequiresTotp(admin)) {
      const totpHmacSecret = getTotpHmacSecret();
      if (!totpHmacSecret) {
        return res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
      }

      const challengeToken = createTotpChallengeToken(admin.id, totpHmacSecret);
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_totp_required',
        adminId: admin.id,
        actorIdentifier: admin.email,
        req,
        result: 'pending',
        reason: 'totp_required',
      });
      return res.json({
        status: 'totp_required',
        challenge_token: challengeToken,
        email: admin.email,
      });
    }

    if (shouldResetLoginProtectionAfterPassword(admin)) {
      await resetLoginProtection({ email, req });
    }

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

router.post('/login/totp', loginIpLimiter, async (req, res) => {
  try {
    if (!requireSecureSessionRequest(req)) {
      clearSessionCookie(res);
      return res.status(400).json(buildAuthErrorResponse(ADMIN_AUTH_CHANNEL_MESSAGE));
    }

    const challengeToken = typeof req.body?.challenge_token === 'string' ? req.body.challenge_token : '';
    const code = typeof req.body?.code === 'string' ? req.body.code : '';
    const totpHmacSecret = getTotpHmacSecret();
    const challenge = totpHmacSecret
      ? parseTotpChallengeToken(challengeToken, totpHmacSecret)
      : null;

    let admin = null;
    if (challenge) {
      const result = await pool.query('SELECT * FROM admins WHERE id = $1', [challenge.admin_id]);
      admin = result.rows[0] || null;
    }

    const email = admin?.email || null;
    const activeLock = await getActiveLoginLock({ email, req });
    if (activeLock) {
      const locked = lockedSecondFactorOutcome({
        email,
        adminId: admin?.id || null,
        activeLock,
      });
      await auditAdminEvent(buildLoginLockedAuditPayload({
        email,
        req,
        activeLock,
      }));
      const http = secondFactorHttpResponse(locked);
      return res.status(http.status).json(http.body);
    }

    const totpValid = Boolean(
      admin?.totp_enabled && admin?.totp_secret && verifyTotp(admin.totp_secret, code)
    );
    const outcome = decideSecondFactorAttempt({ challenge, admin, totpValid });
    const protection = await applySecondFactorProtection(outcome, {
      req,
      registerLoginFailure,
      resetLoginProtection,
    });

    if (outcome.kind !== 'success') {
      if (outcome.reason === 'account_disabled') {
        await auditAdminEvent(buildLoginRejectedAuditPayload({
          email: outcome.email,
          req,
          reason: 'account_disabled',
        }));
      } else if (outcome.reason === 'totp_invalid') {
        await auditAdminEvent({
          component: 'auth',
          eventType: 'login_totp_failed',
          adminId: outcome.adminId,
          actorIdentifier: outcome.email,
          req,
          result: 'fail',
          reason: 'totp_invalid',
        });
      } else {
        await auditAdminEvent(buildLoginFailedAuditPayload({
          email: outcome.email,
          req,
          guards: protection.guards,
          adminId: outcome.adminId,
        }));
      }

      const http = secondFactorHttpResponse(outcome);
      return res.status(http.status).json(http.body);
    }

    const session = await createSession(admin, req);
    setSessionCookie(res, session.token, session.metadata.session.expires_at);
    await auditAdminEvent(buildLoginSucceededAuditPayload({ admin, req }));
    await auditAdminEvent(buildSessionCreatedAuditPayload({ admin, req, session }));

    return res.json(buildAdminAuthResponse(
      {
        token: session.token,
        metadata: toSessionResponsePayload(session.metadata),
      },
      createBearerSessionToken
    ));
  } catch (err) {
    console.error('[AUTH] TOTP login error:', err.message);
    return res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
  }
});

router.get('/2fa/status', auth, requirePermission('security.self'), async (req, res) => {
  const result = await pool.query(
    'SELECT totp_enabled FROM admins WHERE id = $1',
    [req.admin.id]
  );
  return res.json({
    totp_enabled: Boolean(result.rows[0]?.totp_enabled),
  });
});

router.post('/2fa/setup', auth, requirePermission('security.self'), async (req, res) => {
  try {
    const secret = generateTotpSecret();
    await pool.query(
      'UPDATE admins SET totp_pending_secret = $1 WHERE id = $2',
      [secret, req.admin.id]
    );
    return res.json({
      secret,
      otpauth_url: buildOtpauthUri({ secret, email: req.admin.email }),
    });
  } catch (err) {
    console.error('[AUTH] 2FA setup error:', err.message);
    return res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
  }
});

router.post('/2fa/enable', auth, requirePermission('security.self'), async (req, res) => {
  try {
    const code = typeof req.body?.code === 'string' ? req.body.code : '';
    const result = await pool.query(
      'SELECT totp_pending_secret, email FROM admins WHERE id = $1',
      [req.admin.id]
    );
    const pending = result.rows[0]?.totp_pending_secret;
    if (!pending || !verifyTotp(pending, code)) {
      return res.status(400).json(buildAuthErrorResponse('Codigo 2FA invalido.'));
    }

    await pool.query(
      `UPDATE admins
          SET totp_secret = totp_pending_secret,
              totp_enabled = TRUE,
              totp_pending_secret = NULL
        WHERE id = $1`,
      [req.admin.id]
    );

    await auditAdminEvent({
      component: 'auth',
      eventType: 'totp_enabled',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'totp_enabled',
    });

    return res.json({ totp_enabled: true });
  } catch (err) {
    console.error('[AUTH] 2FA enable error:', err.message);
    return res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
  }
});

router.post('/2fa/disable', auth, requirePermission('security.self'), async (req, res) => {
  try {
    const password = typeof req.body?.password === 'string' ? req.body.password : '';
    const code = typeof req.body?.code === 'string' ? req.body.code : '';
    const result = await pool.query('SELECT * FROM admins WHERE id = $1', [req.admin.id]);
    const admin = result.rows[0];
    if (!admin) {
      return res.status(404).json(buildAuthErrorResponse('Admin nao encontrado.'));
    }

    const validPassword = await bcrypt.compare(password, admin.password_hash);
    if (!validPassword) {
      return res.status(401).json(buildAuthErrorResponse(ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE));
    }

    if (admin.totp_enabled && admin.totp_secret && !verifyTotp(admin.totp_secret, code)) {
      return res.status(401).json(buildAuthErrorResponse('Codigo 2FA invalido.'));
    }

    await pool.query(
      `UPDATE admins
          SET totp_secret = NULL,
              totp_pending_secret = NULL,
              totp_enabled = FALSE
        WHERE id = $1`,
      [req.admin.id]
    );

    await auditAdminEvent({
      component: 'auth',
      eventType: 'totp_disabled',
      adminId: req.admin.id,
      actorIdentifier: req.admin.email,
      req,
      result: 'success',
      reason: 'totp_disabled',
    });

    return res.json({ totp_enabled: false });
  } catch (err) {
    console.error('[AUTH] 2FA disable error:', err.message);
    return res.status(500).json(buildAuthErrorResponse(ADMIN_INTERNAL_ERROR_MESSAGE));
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
