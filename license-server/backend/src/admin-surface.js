const rateLimit = require('express-rate-limit');
const pool = require('./db');
const { getClientIp } = require('./session');

const ADMIN_PUBLIC_ORIGIN = 'https://license.systemup.inf.br';
const ADMIN_DEV_ORIGINS = ['http://localhost:5173', 'http://127.0.0.1:5173'];

const LOGIN_IP_RATE_WINDOW_MS = 10 * 60 * 1000;
const LOGIN_IP_RATE_MAX = 10;
const LOGIN_IDENTITY_RATE_WINDOW_MS = 10 * 60 * 1000;
const LOGIN_IDENTITY_RATE_MAX = 5;
const LOGIN_GUARD_WINDOW_MS = 15 * 60 * 1000;
const LOGIN_ACCOUNT_LOCK_THRESHOLD = 5;
const LOGIN_IP_LOCK_THRESHOLD = 10;
const LOGIN_LOCK_DURATION_MS = 15 * 60 * 1000;

const ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE = 'Credenciais invalidas';
const ADMIN_AUTH_RATE_LIMIT_MESSAGE = 'Demasiadas tentativas. Tente novamente mais tarde.';
const ADMIN_AUTH_LOCKED_MESSAGE = 'Demasiadas tentativas. Tente novamente mais tarde.';
const ADMIN_AUTH_ORIGIN_MESSAGE = 'Origem administrativa nao autorizada.';
const ADMIN_AUTH_CHANNEL_MESSAGE = 'Canal administrativo invalido.';
const ADMIN_AUTH_REQUIRED_MESSAGE = 'Autenticacao necessaria.';
const ADMIN_INTERNAL_ERROR_MESSAGE = 'Erro interno.';

function normalizeAdminEmail(email) {
  return typeof email === 'string' ? email.trim().toLowerCase() : '';
}

function getAllowedAdminOrigins() {
  const origins = new Set([ADMIN_PUBLIC_ORIGIN]);

  if (process.env.NODE_ENV !== 'production') {
    ADMIN_DEV_ORIGINS.forEach((origin) => origins.add(origin));
  }

  return origins;
}

function isAdminApiPath(pathname = '') {
  return pathname.startsWith('/api/auth/')
    || pathname.startsWith('/api/dashboard')
    || pathname.startsWith('/api/licenses')
    || pathname.startsWith('/api/customers');
}

function getAuditContext(req) {
  return {
    ipAddress: getClientIp(req),
    userAgent: req?.headers?.['user-agent'] || null,
    route: req?.originalUrl || req?.path || null,
    origin: req?.headers?.origin || null,
  };
}

function emitAuditLine(payload) {
  const line = {
    timestamp_utc: new Date().toISOString(),
    surface: 'admin',
    ...payload,
  };

  console.log(`[AUDIT] ${JSON.stringify(line)}`);
}

async function ensureAdminSurfaceSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS admin_audit_log (
      id SERIAL PRIMARY KEY,
      component VARCHAR(64) NOT NULL,
      event_type VARCHAR(64) NOT NULL,
      actor_admin_id INTEGER REFERENCES admins(id) ON DELETE SET NULL,
      actor_identifier VARCHAR(255),
      ip_address VARCHAR(45),
      user_agent VARCHAR(255),
      route VARCHAR(255),
      result VARCHAR(32) NOT NULL,
      reason VARCHAR(255),
      metadata JSONB,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_audit_log_created_at
      ON admin_audit_log(created_at)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_audit_log_event_type
      ON admin_audit_log(event_type)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_audit_log_actor_admin
      ON admin_audit_log(actor_admin_id)
  `);

  await pool.query(`
    CREATE TABLE IF NOT EXISTS admin_login_guards (
      id SERIAL PRIMARY KEY,
      scope_type VARCHAR(32) NOT NULL,
      scope_key VARCHAR(255) NOT NULL,
      failure_count INTEGER NOT NULL DEFAULT 0,
      first_failure_at TIMESTAMPTZ,
      last_failure_at TIMESTAMPTZ,
      locked_until TIMESTAMPTZ,
      last_success_at TIMESTAMPTZ,
      UNIQUE(scope_type, scope_key)
    )
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_login_guards_locked_until
      ON admin_login_guards(locked_until)
  `);
}

async function auditAdminEvent({
  component,
  eventType,
  adminId = null,
  actorIdentifier = null,
  req = null,
  result,
  reason = null,
  metadata = null,
  client = null,
  strict = false,
}) {
  const context = getAuditContext(req);
  const queryable = client || pool;

  emitAuditLine({
    component,
    event_type: eventType,
    actor_admin_id: adminId,
    actor_identifier: actorIdentifier,
    ip_address: context.ipAddress,
    route: context.route,
    result,
    reason,
  });

  try {
    await queryable.query(
      `INSERT INTO admin_audit_log (
          component,
          event_type,
          actor_admin_id,
          actor_identifier,
          ip_address,
          user_agent,
          route,
          result,
          reason,
          metadata
        )
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10::jsonb)`,
      [
        component,
        eventType,
        adminId,
        actorIdentifier,
        context.ipAddress,
        context.userAgent,
        context.route,
        result,
        reason,
        metadata ? JSON.stringify(metadata) : null,
      ]
    );
  } catch (err) {
    console.error('[AUDIT] Persist error:', err.message);

    if (strict) {
      throw err;
    }
  }
}

function adminNoStoreMiddleware(req, res, next) {
  if (isAdminApiPath(req.path)) {
    res.setHeader('Cache-Control', 'no-store');
  }

  next();
}

async function enforceAdminOrigin(req, res, next) {
  if (!isAdminApiPath(req.path)) {
    return next();
  }

  const origin = req.headers.origin;
  if (!origin) {
    return next();
  }

  res.setHeader('Vary', 'Origin');

  if (getAllowedAdminOrigins().has(origin)) {
    return next();
  }

  await auditAdminEvent({
    component: 'admin-surface',
    eventType: 'origin_denied',
    actorIdentifier: normalizeAdminEmail(req.body?.email),
    req,
    result: 'denied',
    reason: 'origin_not_allowed',
    metadata: { allowed_origins: Array.from(getAllowedAdminOrigins()) },
  });

  return res.status(403).json({ error: ADMIN_AUTH_ORIGIN_MESSAGE });
}

function buildLoginLimiter({ name, windowMs, max, keyGenerator }) {
  return rateLimit({
    windowMs,
    max,
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator,
    handler: async (req, res) => {
      await auditAdminEvent({
        component: 'auth',
        eventType: 'login_rate_limited',
        actorIdentifier: normalizeAdminEmail(req.body?.email),
        req,
        result: 'blocked',
        reason: name,
      });

      res.status(429).json({ error: ADMIN_AUTH_RATE_LIMIT_MESSAGE });
    },
  });
}

const loginIpLimiter = buildLoginLimiter({
  name: 'login_ip_rate_limit',
  windowMs: LOGIN_IP_RATE_WINDOW_MS,
  max: LOGIN_IP_RATE_MAX,
  keyGenerator: (req) => getClientIp(req) || 'unknown',
});

const loginIdentityLimiter = buildLoginLimiter({
  name: 'login_identity_rate_limit',
  windowMs: LOGIN_IDENTITY_RATE_WINDOW_MS,
  max: LOGIN_IDENTITY_RATE_MAX,
  keyGenerator: (req) => {
    const email = normalizeAdminEmail(req.body?.email) || 'unknown';
    return `${email}:${getClientIp(req) || 'unknown'}`;
  },
});

async function getLoginGuard(scopeType, scopeKey) {
  const result = await pool.query(
    `SELECT scope_type, scope_key, failure_count, first_failure_at, last_failure_at, locked_until
       FROM admin_login_guards
      WHERE scope_type = $1 AND scope_key = $2`,
    [scopeType, scopeKey]
  );

  return result.rows[0] || null;
}

async function updateLoginGuard(scopeType, scopeKey, threshold, now) {
  const guard = await getLoginGuard(scopeType, scopeKey);
  const firstFailureAt = guard?.first_failure_at ? new Date(guard.first_failure_at) : null;
  const withinWindow = firstFailureAt && (now.getTime() - firstFailureAt.getTime()) <= LOGIN_GUARD_WINDOW_MS;
  const failureCount = withinWindow ? guard.failure_count + 1 : 1;
  const nextFirstFailureAt = withinWindow ? firstFailureAt : now;
  const lockedUntil = failureCount >= threshold
    ? new Date(now.getTime() + LOGIN_LOCK_DURATION_MS)
    : null;

  await pool.query(
    `INSERT INTO admin_login_guards (
        scope_type,
        scope_key,
        failure_count,
        first_failure_at,
        last_failure_at,
        locked_until,
        last_success_at
      )
      VALUES ($1, $2, $3, $4, $5, $6, NULL)
      ON CONFLICT (scope_type, scope_key)
      DO UPDATE SET
        failure_count = EXCLUDED.failure_count,
        first_failure_at = EXCLUDED.first_failure_at,
        last_failure_at = EXCLUDED.last_failure_at,
        locked_until = EXCLUDED.locked_until`,
    [scopeType, scopeKey, failureCount, nextFirstFailureAt, now, lockedUntil]
  );

  return {
    scopeType,
    scopeKey,
    failureCount,
    lockedUntil,
  };
}

async function registerLoginFailure({ email, req }) {
  const now = new Date();
  const ip = getClientIp(req) || 'unknown';
  const normalizedEmail = normalizeAdminEmail(email);
  const results = [];

  results.push(await updateLoginGuard('ip', ip, LOGIN_IP_LOCK_THRESHOLD, now));

  if (normalizedEmail) {
    results.push(await updateLoginGuard('account', normalizedEmail, LOGIN_ACCOUNT_LOCK_THRESHOLD, now));
  }

  return results;
}

async function resetLoginProtection({ email, req }) {
  const ip = getClientIp(req) || 'unknown';
  const normalizedEmail = normalizeAdminEmail(email);
  const keys = [['ip', ip]];

  if (normalizedEmail) {
    keys.push(['account', normalizedEmail]);
  }

  for (const [scopeType, scopeKey] of keys) {
    await pool.query(
      `INSERT INTO admin_login_guards (
          scope_type,
          scope_key,
          failure_count,
          first_failure_at,
          last_failure_at,
          locked_until,
          last_success_at
        )
        VALUES ($1, $2, 0, NULL, NULL, NULL, NOW())
        ON CONFLICT (scope_type, scope_key)
        DO UPDATE SET
          failure_count = 0,
          first_failure_at = NULL,
          last_failure_at = NULL,
          locked_until = NULL,
          last_success_at = NOW()`,
      [scopeType, scopeKey]
    );
  }
}

async function getActiveLoginLock({ email, req }) {
  const ip = getClientIp(req) || 'unknown';
  const normalizedEmail = normalizeAdminEmail(email);
  const now = Date.now();
  const guards = [await getLoginGuard('ip', ip)];

  if (normalizedEmail) {
    guards.push(await getLoginGuard('account', normalizedEmail));
  }

  for (const guard of guards) {
    if (!guard?.locked_until) {
      continue;
    }

    const lockedUntil = new Date(guard.locked_until);
    if (lockedUntil.getTime() > now) {
      return {
        scopeType: guard.scope_type,
        scopeKey: guard.scope_key,
        lockedUntil,
      };
    }
  }

  return null;
}

module.exports = {
  ADMIN_AUTH_CHANNEL_MESSAGE,
  ADMIN_AUTH_INVALID_CREDENTIALS_MESSAGE,
  ADMIN_AUTH_LOCKED_MESSAGE,
  ADMIN_AUTH_RATE_LIMIT_MESSAGE,
  ADMIN_AUTH_REQUIRED_MESSAGE,
  ADMIN_INTERNAL_ERROR_MESSAGE,
  adminNoStoreMiddleware,
  auditAdminEvent,
  enforceAdminOrigin,
  ensureAdminSurfaceSchema,
  getActiveLoginLock,
  getAllowedAdminOrigins,
  isAdminApiPath,
  loginIdentityLimiter,
  loginIpLimiter,
  normalizeAdminEmail,
  registerLoginFailure,
  resetLoginProtection,
};
