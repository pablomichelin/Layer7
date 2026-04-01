const crypto = require('crypto');
const pool = require('./db');

const SESSION_COOKIE_NAME = 'layer7_admin_session';
const SESSION_IDLE_TIMEOUT_MS = 30 * 60 * 1000;
const SESSION_ABSOLUTE_TIMEOUT_MS = 8 * 60 * 60 * 1000;
const SESSION_RENEW_WINDOW_MS = 5 * 60 * 1000;

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

function getClientIp(req) {
  const forwardedFor = req.headers['x-forwarded-for'];
  if (typeof forwardedFor === 'string' && forwardedFor.trim()) {
    return forwardedFor.split(',')[0].trim();
  }

  return req.ip || req.socket?.remoteAddress || null;
}

function buildSessionMetadata(row) {
  const createdAt = new Date(row.created_at);
  const expiresAt = new Date(row.expires_at);
  const lastSeenAt = row.last_seen_at ? new Date(row.last_seen_at) : createdAt;
  const absoluteExpiresAt = new Date(createdAt.getTime() + SESSION_ABSOLUTE_TIMEOUT_MS);

  return {
    admin: {
      id: row.admin_id,
      email: row.email,
      name: row.name,
    },
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

async function revokeActiveSessionsForAdmin(adminId) {
  await pool.query(
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

  await revokeActiveSessionsForAdmin(admin.id);

  const result = await pool.query(
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

  const sessionRow = result.rows[0];
  return {
    token,
    metadata: {
      admin: {
        id: admin.id,
        email: admin.email,
        name: admin.name,
      },
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
}

async function resolveSession(req, res) {
  const token = getSessionTokenFromRequest(req);
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
        a.name
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
    clearSessionCookie(res);
    return null;
  }

  const metadata = buildSessionMetadata(row);
  const now = new Date();

  if (now >= metadata.session.expires_at || now >= metadata.session.absolute_expires_at) {
    await pool.query(
      `UPDATE admin_sessions
          SET revoked_at = COALESCE(revoked_at, NOW())
        WHERE id = $1`,
      [row.id]
    );
    clearSessionCookie(res);
    return null;
  }

  const timeUntilIdleExpiry = metadata.session.expires_at.getTime() - now.getTime();
  let nextExpiresAt = metadata.session.expires_at;

  if (timeUntilIdleExpiry <= SESSION_RENEW_WINDOW_MS) {
    nextExpiresAt = new Date(Math.min(
      now.getTime() + SESSION_IDLE_TIMEOUT_MS,
      metadata.session.absolute_expires_at.getTime()
    ));

    await pool.query(
      `UPDATE admin_sessions
          SET last_seen_at = NOW(),
              expires_at = $2
        WHERE id = $1`,
      [row.id, nextExpiresAt]
    );

    metadata.session.last_seen_at = now;
    metadata.session.expires_at = nextExpiresAt;
    setSessionCookie(res, token, nextExpiresAt);
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
    token,
    metadata,
  };
}

function requireSecureSessionRequest(req) {
  return req.secure;
}

function toSessionResponsePayload(metadata) {
  return {
    admin: metadata.admin,
    session: {
      idle_timeout_minutes: SESSION_IDLE_TIMEOUT_MS / 60000,
      absolute_timeout_hours: SESSION_ABSOLUTE_TIMEOUT_MS / 3600000,
      created_at: metadata.session.created_at.toISOString(),
      last_seen_at: metadata.session.last_seen_at.toISOString(),
      expires_at: metadata.session.expires_at.toISOString(),
      absolute_expires_at: metadata.session.absolute_expires_at.toISOString(),
    },
  };
}

module.exports = {
  SESSION_COOKIE_NAME,
  clearSessionCookie,
  createSession,
  ensureSessionSchema,
  getClientIp,
  getSessionTokenFromRequest,
  requireSecureSessionRequest,
  resolveSession,
  revokeSessionByToken,
  setSessionCookie,
  toSessionResponsePayload,
};
