const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const TOKEN = 'a'.repeat(64);
const TOKEN_HASH = crypto.createHash('sha256').update(TOKEN).digest('hex');

function liveRow(overrides = {}) {
  const createdAt = new Date();
  return {
    id: 11,
    admin_id: 7,
    created_at: createdAt,
    expires_at: new Date(createdAt.getTime() + 30 * 60 * 1000),
    last_seen_at: createdAt,
    revoked_at: null,
    ip_address: '203.0.113.10',
    user_agent: 'session-totp-metadata-test',
    email: 'owner@example.com',
    name: 'Owner',
    is_owner: true,
    is_active: true,
    permissions: [],
    totp_enabled: true,
    ...overrides,
  };
}

function selectedAliases(sql) {
  const match = String(sql).match(/SELECT([\s\S]*?)FROM/i);
  if (!match) {
    return [];
  }
  return match[1]
    .split(',')
    .map((part) => {
      const trimmed = part.trim();
      const asMatch = trimmed.match(/\bAS\s+(\w+)$/i);
      if (asMatch) {
        return asMatch[1];
      }
      const dotted = trimmed.match(/\.(\w+)$/);
      return dotted ? dotted[1] : trimmed;
    })
    .filter(Boolean);
}

function projectRow(sql, fullRow) {
  const aliases = selectedAliases(sql);
  const projected = {};
  for (const key of aliases) {
    if (Object.prototype.hasOwnProperty.call(fullRow, key)) {
      projected[key] = fullRow[key];
    }
  }
  return projected;
}

function createPool(fullRow) {
  const captured = [];
  return {
    captured,
    async query(sql, params = []) {
      const normalized = String(sql).replace(/\s+/g, ' ').trim();
      captured.push({ sql: normalized, params });

      if (/FROM admin_sessions s\s+JOIN admins a/i.test(normalized)) {
        assert.equal(params[0], TOKEN_HASH);
        return { rows: [projectRow(sql, fullRow)] };
      }

      if (/UPDATE admin_sessions/i.test(normalized)) {
        return { rows: [] };
      }

      throw new Error(`SQL inesperado no teste TOTP metadata: ${normalized.slice(0, 180)}`);
    },
  };
}

function loadSession(pool) {
  const dbPath = require.resolve('./db');
  const sessionPath = require.resolve('./session');
  delete require.cache[sessionPath];
  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: pool,
  };
  return require('./session');
}

function sessionRequest() {
  return {
    ip: '203.0.113.10',
    headers: {
      cookie: `layer7_admin_session=${TOKEN}`,
    },
  };
}

test('P3-2 — GET session reporta totp_enabled true quando o admin tem TOTP ligado', async () => {
  const pool = createPool(liveRow());
  const { resolveSession } = loadSession(pool);
  const session = await resolveSession(sessionRequest(), { cookie() {} });

  assert.equal(session.metadata.admin.totp_enabled, true);
  assert.match(
    pool.captured[0].sql,
    /a\.totp_enabled/,
    'resolveSessionToken tem de SELECT a.totp_enabled'
  );
});

test('P3-2 — GET session reporta totp_enabled false quando o admin nao tem TOTP', async () => {
  const pool = createPool(liveRow({ totp_enabled: false }));
  const { resolveSession } = loadSession(pool);
  const session = await resolveSession(sessionRequest(), { cookie() {} });

  assert.equal(session.metadata.admin.totp_enabled, false);
});

test('P3-2 — omissao de a.totp_enabled no SELECT projecta false apesar da BD verdadeira', async () => {
  const pool = createPool(liveRow());
  const { resolveSession } = loadSession(pool);
  const originalQuery = pool.query.bind(pool);
  pool.query = async (sql, params = []) => {
    const stripped = String(sql).replace(/\s*a\.totp_enabled\s*,?/i, '\n');
    return originalQuery(stripped, params);
  };

  const session = await resolveSession(sessionRequest(), { cookie() {} });
  assert.equal(
    session.metadata.admin.totp_enabled,
    false,
    'prova do bug: SELECT sem a.totp_enabled + toPublicAdmin => false'
  );
});

test('P3-2 — resolveSession preserva TTL, cookie e createSession atomico', () => {
  const source = fs.readFileSync(path.join(__dirname, 'session.js'), 'utf8');
  assert.match(source, /totp_enabled:\s*row\.totp_enabled/);
  assert.match(source, /a\.totp_enabled/);
  assert.match(source, /SESSION_IDLE_TIMEOUT_MS = 30 \* 60 \* 1000/);
  assert.match(source, /SESSION_ABSOLUTE_TIMEOUT_MS = 8 \* 60 \* 60 \* 1000/);
  assert.match(source, /SELECT id FROM admins WHERE id = \$1 FOR UPDATE/);
  assert.match(source, /sameSite: 'strict'/);
  assert.match(source, /httpOnly: true/);
  assert.doesNotMatch(source, /function getSessionMetadata\b/);
});
