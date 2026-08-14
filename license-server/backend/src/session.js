const crypto = require('crypto');
const pool = require('./db');
const { getAdminBearerJwtSecret } = require('./admin-bearer-secret');
const {
  getAdminAccessTokenCandidates: buildAdminAccessTokenCandidates,
  getAdminAccessTokenFromSources,
} = require('./auth-access');
const {
  createBearerSessionToken: createSignedBearerSessionToken,
  extractBearerTokenFromAuthorizationHeader,
  verifyBearerSessionToken,
} = require('./bearer-session-token');
const { getSessionLifecycleDecision } = require('./session-lifecycle');
const { toPublicAdmin } = require('./admin-permissions');

const SESSION_COOKIE_NAME = 'layer7_admin_session';
const SESSION_IDLE_TIMEOUT_MS = 30 * 60 * 1000;
const SESSION_ABSOLUTE_TIMEOUT_MS = 8 * 60 * 60 * 1000;
const SESSION_RENEW_WINDOW_MS = 5 * 60 * 1000;
const ADMIN_PUBLIC_SESSION_HOST = 'license.systemup.inf.br';

function getBearerJwtSecret() {
  return getAdminBearerJwtSecret(process.env) || null;
}

function hashSessionToken(token) {
  return crypto.createHash('sha256').update(token).digest('hex');
}

function generateSessionToken() {
  return crypto.randomBytes(32).toString('hex');
}

function parseCookies(req) {
  const rawCookie = req.headers.cookie || '';
  return rawCookie
    .split(';')
    .map((entry) => entry.trim())
    .filter(Boolean)
    .reduce((acc, entry) => {
      const separator = entry.indexOf('=');
      if (separator === -1) {
        return acc;
      }

      const key = entry.slice(0, separator).trim();
      const value = entry.slice(separator + 1).trim();
      try {
        acc[key] = decodeURIComponent(value);
      } catch {
        acc[key] = value;
      }
      return acc;
    }, {});
}

function getSessionTokenFromRequest(req) {
  const cookies = parseCookies(req);
  return cookies[SESSION_COOKIE_NAME] || null;
}

function getBearerTokenFromRequest(req) {
  return extractBearerTokenFromAuthorizationHeader(
    req.headers.authorization || req.headers.Authorization
  );
}

function verifyAdminBearerSessionToken(token) {
  return verifyBearerSessionToken(token, { secret: getBearerJwtSecret() });
}

function getAdminAccessTokenFromRequest(req) {
  const bearerToken = getBearerTokenFromRequest(req);
  return getAdminAccessTokenFromSources({
    bearerSessionToken: verifyAdminBearerSessionToken(bearerToken),
    sessionToken: getSessionTokenFromRequest(req),
  });
}

function getAdminAccessTokenCandidates(req) {
  const candidates = [];
  const bearerToken = getBearerTokenFromRequest(req);
  const bearerSessionToken = verifyAdminBearerSessionToken(bearerToken);
  const sessionToken = getSessionTokenFromRequest(req);

  return buildAdminAccessTokenCandidates({
    bearerSessionToken,
    sessionToken,
  });
}

function getClientIp(req) {
  if (!req || typeof req !== 'object') {
    return null;
  }

  // P1-2 / BG-128: nunca usar o primeiro hop de X-Forwarded-For (cliente).
  // req.ip respeita trust proxy: 1; o origin substitui XFF por $remote_addr.
  const trusted = typeof req.ip === 'string' ? req.ip.trim() : '';
  if (trusted) {
    return trusted;
  }

  const socketIp = typeof req.socket?.remoteAddress === 'string'
    ? req.socket.remoteAddress.trim()
    : '';
  return socketIp || null;
}

function buildSessionMetadata(row) {
  const createdAt = new Date(row.created_at);
  const expiresAt = new Date(row.expires_at);
  const lastSeenAt = row.last_seen_at ? new Date(row.last_seen_at) : createdAt;
  const absoluteExpiresAt = new Date(createdAt.getTime() + SESSION_ABSOLUTE_TIMEOUT_MS);
  const publicAdmin = toPublicAdmin({
    id: row.admin_id,
    email: row.email,
    name: row.name,
    is_owner: row.is_owner,
    is_active: row.is_active,
    permissions: row.permissions,
    totp_enabled: row.totp_enabled,
  });

  return {
    admin: publicAdmin,
    session: {
      id: row.id,
      created_at: createdAt,
      last_seen_at: lastSeenAt,
      expires_at: expiresAt,
      absolute_expires_at: absoluteExpiresAt,
      ip_address: row.ip_address,
      user_agent: row.user_agent,
    },
  };
}

function setSessionCookie(res, token, expiresAt) {
  const maxAge = Math.max(0, expiresAt.getTime() - Date.now());

  res.cookie(SESSION_COOKIE_NAME, token, {
    httpOnly: true,
    secure: true,
    sameSite: 'strict',
    path: '/',
    maxAge,
  });
}

function clearSessionCookie(res) {
  res.cookie(SESSION_COOKIE_NAME, '', {
    httpOnly: true,
    secure: true,
    sameSite: 'strict',
    path: '/',
    expires: new Date(0),
  });
}

async function ensureSessionSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS admin_sessions (
      id SERIAL PRIMARY KEY,
      admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
      session_token_hash VARCHAR(64) UNIQUE NOT NULL,
      created_at TIMESTAMP NOT NULL DEFAULT NOW(),
      expires_at TIMESTAMP NOT NULL,
      last_seen_at TIMESTAMP NOT NULL DEFAULT NOW(),
      revoked_at TIMESTAMP,
      ip_address VARCHAR(45),
      user_agent VARCHAR(255)
    )
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_sessions_admin
      ON admin_sessions(admin_id)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_sessions_expires
      ON admin_sessions(expires_at)
  `);
  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_sessions_revoked
      ON admin_sessions(revoked_at)
  `);
}

async function revokeSessionByToken(token) {
  if (!token) {
    return;
  }

  await pool.query(
    `UPDATE admin_sessions
        SET revoked_at = COALESCE(revoked_at, NOW())
      WHERE session_token_hash = $1`,
    [hashSessionToken(token)]
  );
}

async function revokeActiveSessionsForAdmin(adminId, executor = pool) {
  await executor.query(
    `UPDATE admin_sessions
        SET revoked_at = COALESCE(revoked_at, NOW())
      WHERE admin_id = $1
        AND revoked_at IS NULL`,
    [adminId]
  );
}

async function createSession(admin, req) {
  const token = generateSessionToken();
  const tokenHash = hashSessionToken(token);
  const now = new Date();
  const expiresAt = new Date(now.getTime() + SESSION_IDLE_TIMEOUT_MS);
  const client = await pool.connect();

  try {
    await client.query('BEGIN');
    // P3-1: revoke+insert na mesma tx sem lock do admin nao serializa
    // (READ COMMITTED). Unique parcial fica fora — exigiria limpar duplicados.
    const locked = await client.query(
      'SELECT id FROM admins WHERE id = $1 FOR UPDATE',
      [admin.id]
    );
    if (locked.rows.length === 0) {
      throw new Error('Admin inexistente para criar sessao');
    }

    await revokeActiveSessionsForAdmin(admin.id, client);

    const result = await client.query(
      `INSERT INTO admin_sessions (
          admin_id,
          session_token_hash,
          created_at,
          expires_at,
          last_seen_at,
          ip_address,
          user_agent
        )
        VALUES ($1, $2, $3, $4, $3, $5, $6)
        RETURNING id, created_at, expires_at, last_seen_at, ip_address, user_agent`,
      [
        admin.id,
        tokenHash,
        now,
        expiresAt,
        getClientIp(req),
        req.headers['user-agent'] || null,
      ]
    );
    await client.query('COMMIT');

    const sessionRow = result.rows[0];
    return {
      token,
      metadata: {
        admin: toPublicAdmin(admin),
        session: {
          id: sessionRow.id,
          created_at: new Date(sessionRow.created_at),
          last_seen_at: new Date(sessionRow.last_seen_at),
          expires_at: new Date(sessionRow.expires_at),
          absolute_expires_at: new Date(new Date(sessionRow.created_at).getTime() + SESSION_ABSOLUTE_TIMEOUT_MS),
          ip_address: sessionRow.ip_address,
          user_agent: sessionRow.user_agent,
        },
      },
    };
  } catch (err) {
    try {
      await client.query('ROLLBACK');
    } catch {
      // ignore rollback errors
    }
    throw err;
  } finally {
    client.release();
  }
}

async function resolveSessionToken({ token, source }, res) {
  if (!token) {
    return null;
  }

  const result = await pool.query(
    `SELECT
        s.id,
        s.admin_id,
        s.created_at,
        s.expires_at,
        s.last_seen_at,
        s.revoked_at,
        s.ip_address,
        s.user_agent,
        a.email,
        a.name,
        a.is_owner,
        a.is_active,
        a.permissions
      FROM admin_sessions s
      JOIN admins a ON a.id = s.admin_id
      WHERE s.session_token_hash = $1`,
    [hashSessionToken(token)]
  );

  if (result.rows.length === 0) {
    return null;
  }

  const row = result.rows[0];
  if (row.revoked_at) {
    if (source === 'cookie') {
      clearSessionCookie(res);
    }
    return null;
  }

  if (row.is_active === false) {
    await pool.query(
      `UPDATE admin_sessions
          SET revoked_at = COALESCE(revoked_at, NOW())
        WHERE id = $1`,
      [row.id]
    );
    if (source === 'cookie') {
      clearSessionCookie(res);
    }
    return null;
  }

  const metadata = buildSessionMetadata(row);
  const now = new Date();
  const lifecycle = getSessionLifecycleDecision({
    now,
    expiresAt: metadata.session.expires_at,
    absoluteExpiresAt: metadata.session.absolute_expires_at,
    renewWindowMs: SESSION_RENEW_WINDOW_MS,
    idleTimeoutMs: SESSION_IDLE_TIMEOUT_MS,
  });

  if (lifecycle.action === 'expired') {
    await pool.query(
      `UPDATE admin_sessions
          SET revoked_at = COALESCE(revoked_at, NOW())
        WHERE id = $1`,
      [row.id]
    );
    if (source === 'cookie') {
      clearSessionCookie(res);
    }
    return null;
  }

  if (lifecycle.action === 'renew') {
    await pool.query(
      `UPDATE admin_sessions
          SET last_seen_at = NOW(),
              expires_at = $2
        WHERE id = $1`,
      [row.id, lifecycle.nextExpiresAt]
    );

    metadata.session.last_seen_at = now;
    metadata.session.expires_at = lifecycle.nextExpiresAt;
    if (source === 'cookie') {
      setSessionCookie(res, token, lifecycle.nextExpiresAt);
    }
  } else {
    await pool.query(
      `UPDATE admin_sessions
          SET last_seen_at = NOW()
        WHERE id = $1`,
      [row.id]
    );
    metadata.session.last_seen_at = now;
  }

  return {
    authSource: source,
    token,
    metadata,
  };
}

async function resolveSession(req, res) {
  const candidates = getAdminAccessTokenCandidates(req);
  if (candidates.length === 0) {
    return null;
  }

  for (const candidate of candidates) {
    const session = await resolveSessionToken(candidate, res);
    if (session) {
      return session;
    }
  }

  return null;
}

function normalizeSessionChannelHost(value) {
  if (typeof value !== 'string') {
    return '';
  }
  const trimmed = value.trim();
  if (!trimmed || trimmed.includes(',')) {
    return '';
  }
  return trimmed.split(':')[0].trim().toLowerCase();
}

function getSessionChannelHost(req) {
  const forwarded = normalizeSessionChannelHost(req?.headers?.['x-forwarded-host']);
  if (forwarded) {
    return forwarded;
  }
  return normalizeSessionChannelHost(req?.headers?.host);
}

function isExplicitNonProductionEnv(env = process.env) {
  return env.NODE_ENV === 'development' || env.NODE_ENV === 'test';
}

function requireSecureSessionRequest(req, env = process.env) {
  // P2-3: req.secure e spoofable com trust proxy + X-Forwarded-Proto.
  // O origin passa a emitir $scheme (HTTP). O canal oficial F2.1 e o
  // host publico; IP/localhost em producao ficam fail-closed.
  const host = getSessionChannelHost(req);
  if (host === ADMIN_PUBLIC_SESSION_HOST) {
    return true;
  }
  if (isExplicitNonProductionEnv(env) && (host === 'localhost' || host === '127.0.0.1')) {
    return true;
  }
  return false;
}

function toSessionResponsePayload(metadata) {
  return {
    admin: metadata.admin,
    session: {
      id: metadata.session.id,
      idle_timeout_minutes: SESSION_IDLE_TIMEOUT_MS / 60000,
      absolute_timeout_hours: SESSION_ABSOLUTE_TIMEOUT_MS / 3600000,
      created_at: metadata.session.created_at.toISOString(),
      last_seen_at: metadata.session.last_seen_at.toISOString(),
      expires_at: metadata.session.expires_at.toISOString(),
      absolute_expires_at: metadata.session.absolute_expires_at.toISOString(),
    },
  };
}

function createBearerSessionToken(session) {
  const secret = getBearerJwtSecret();
  if (!secret) {
    return null;
  }

  return createSignedBearerSessionToken({
    secret,
    sessionToken: session.token,
    admin: session.metadata.admin,
    session: session.metadata.session,
  });
}

module.exports = {
  SESSION_COOKIE_NAME,
  clearSessionCookie,
  createBearerSessionToken,
  createSession,
  getAdminAccessTokenFromRequest,
  getBearerTokenFromRequest,
  ensureSessionSchema,
  getClientIp,
  getSessionChannelHost,
  getSessionTokenFromRequest,
  requireSecureSessionRequest,
  resolveSession,
  revokeActiveSessionsForAdmin,
  revokeSessionByToken,
  setSessionCookie,
  toSessionResponsePayload,
};
