const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const bcrypt = require('bcryptjs');

const PASSWORD_10 = '0123456789';
const PASSWORD_12 = '0123456789ab';
const EXISTING_HASH = 'existing-hash-unchanged';

function createResponse() {
  return {
    statusCode: 200,
    body: null,
    status(code) {
      this.statusCode = code;
      return this;
    },
    json(payload) {
      this.body = payload;
      return this;
    },
  };
}

function ownerAdmin() {
  return {
    id: 1,
    email: 'owner@example.com',
    name: 'Owner',
    is_owner: true,
    is_active: true,
    permissions: ['*'],
  };
}

function technicianAdmin() {
  return {
    id: 8,
    email: 'tech@example.com',
    name: 'Tech',
    is_owner: false,
    is_active: true,
    permissions: ['licenses.read'],
  };
}

function existingTechnician(overrides = {}) {
  return {
    id: 22,
    email: 'kept@example.com',
    name: 'Kept',
    password_hash: EXISTING_HASH,
    is_owner: false,
    is_active: true,
    permissions: ['licenses.read'],
    totp_enabled: false,
    created_at: new Date('2026-08-01T00:00:00Z'),
    ...overrides,
  };
}

function loadUsersRouter({ admin = ownerAdmin(), existing = existingTechnician() } = {}) {
  const dbPath = require.resolve('../db');
  const authPath = require.resolve('../auth');
  const surfacePath = require.resolve('../admin-surface');
  const sessionPath = require.resolve('../session');
  const bcryptPath = require.resolve('bcryptjs');
  const usersPath = require.resolve('./users');
  const realSurface = require('../admin-surface');
  const realSession = require('../session');

  const queries = [];
  const hashes = [];
  const audits = [];
  const revokes = [];

  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: {
      async query(sql, params = []) {
        const normalized = String(sql).replace(/\s+/g, ' ').trim();
        queries.push({ sql: normalized, params });

        if (/^INSERT INTO admins/i.test(normalized)) {
          return {
            rows: [{
              id: 42,
              email: params[0],
              name: params[1],
              is_owner: false,
              is_active: true,
              permissions: JSON.parse(params[3] || '[]'),
              totp_enabled: false,
              created_at: new Date('2026-08-14T00:00:00Z'),
            }],
          };
        }

        if (/SELECT \* FROM admins WHERE id = \$1/i.test(normalized)) {
          if (Number(params[0]) === existing.id) {
            return { rows: [existing] };
          }
          return { rows: [] };
        }

        if (/^UPDATE admins/i.test(normalized)) {
          const next = { ...existing };
          if (/name =/.test(normalized)) {
            next.name = params[0];
          }
          if (/password_hash =/.test(normalized)) {
            next.password_hash = params.find((value) => typeof value === 'string' && value.startsWith('$2'))
              || params[params.length - 2];
          }
          return {
            rows: [{
              id: next.id,
              email: next.email,
              name: next.name,
              is_owner: next.is_owner,
              is_active: next.is_active,
              permissions: next.permissions,
              totp_enabled: next.totp_enabled,
              created_at: next.created_at,
              password_hash: next.password_hash,
            }],
          };
        }

        throw new Error(`SQL inesperado no teste P3-3B: ${normalized.slice(0, 160)}`);
      },
    },
  };

  require.cache[authPath] = {
    id: authPath,
    filename: authPath,
    loaded: true,
    exports: (req, _res, next) => {
      if (admin) {
        req.admin = admin;
      }
      return next();
    },
  };

  require.cache[surfacePath] = {
    id: surfacePath,
    filename: surfacePath,
    loaded: true,
    exports: {
      ...realSurface,
      auditAdminEvent: async (payload) => {
        audits.push(payload);
      },
    },
  };

  require.cache[sessionPath] = {
    id: sessionPath,
    filename: sessionPath,
    loaded: true,
    exports: {
      ...realSession,
      revokeActiveSessionsForAdmin: async (adminId) => {
        revokes.push(adminId);
      },
    },
  };

  require.cache[bcryptPath] = {
    id: bcryptPath,
    filename: bcryptPath,
    loaded: true,
    exports: {
      ...bcrypt,
      hash: async (password, rounds) => {
        hashes.push({ password, rounds });
        return `$2a$12$hashed-${password}`;
      },
    },
  };

  delete require.cache[usersPath];
  const router = require('./users');

  return {
    router,
    queries,
    hashes,
    audits,
    revokes,
  };
}

function findRoute(router, method, routePath) {
  const layer = router.stack.find((item) => (
    item.route && item.route.path === routePath && item.route.methods[method]
  ));
  assert.ok(layer, `rota ${method.toUpperCase()} ${routePath} tem de existir`);
  return layer.route.stack[layer.route.stack.length - 1].handle;
}

async function invoke(router, { method, routePath, body = {}, params = {}, admin = ownerAdmin() }) {
  const handler = findRoute(router, method, routePath);
  const req = {
    body,
    params,
    admin,
    originalUrl: `/api/users${routePath === '/' ? '' : routePath}`,
    path: routePath,
  };
  const res = createResponse();
  await handler(req, res, (err) => {
    if (err) {
      throw err;
    }
  });
  return res;
}

async function invokeThroughStack(router, { method, url, body = {} }) {
  return new Promise((resolve, reject) => {
    const req = {
      method,
      url,
      originalUrl: url,
      path: url,
      body,
      headers: {},
      get(name) {
        return this.headers[String(name).toLowerCase()];
      },
    };
    const res = createResponse();
    const finish = () => resolve(res);
    const originalJson = res.json.bind(res);
    res.json = (payload) => {
      originalJson(payload);
      finish();
      return res;
    };
    router.handle(req, res, (err) => {
      if (err) {
        reject(err);
        return;
      }
      finish();
    });
  });
}

test('P3-3B — POST /api/users com password 10 e 400', async () => {
  const harness = loadUsersRouter();
  const res = await invoke(harness.router, {
    method: 'post',
    routePath: '/',
    body: {
      email: 'short@example.com',
      name: 'Short',
      password: PASSWORD_10,
      permissions: ['licenses.read'],
    },
  });

  assert.equal(PASSWORD_10.length, 10);
  assert.equal(res.statusCode, 400);
  assert.deepEqual(res.body, { error: 'Password deve ter pelo menos 12 caracteres.' });
  assert.equal(harness.hashes.length, 0);
  assert.equal(harness.queries.length, 0);
  assert.equal(harness.audits.length, 0);
});

test('P3-3B — POST /api/users com password 12 e 201 e nao cria owner', async () => {
  const harness = loadUsersRouter();
  const res = await invoke(harness.router, {
    method: 'post',
    routePath: '/',
    body: {
      email: 'ok@example.com',
      name: 'Ok Tech',
      password: PASSWORD_12,
      permissions: ['licenses.read', 'users.manage'],
      is_owner: true,
    },
  });

  assert.equal(PASSWORD_12.length, 12);
  assert.equal(res.statusCode, 201);
  assert.equal(res.body.user.email, 'ok@example.com');
  assert.equal(res.body.user.is_owner, false);
  assert.deepEqual(res.body.user.permissions, ['licenses.read']);
  assert.equal(harness.hashes.length, 1);
  assert.equal(harness.hashes[0].password, PASSWORD_12);
  assert.equal(harness.hashes[0].rounds, 12);
  assert.match(harness.queries[0].sql, /INSERT INTO admins/);
  assert.match(harness.queries[0].sql, /is_owner, is_active/);
  assert.match(harness.queries[0].sql, /FALSE, TRUE/);
  assert.equal(harness.audits[0].eventType, 'user_created');
});

test('P3-3B — PUT com password 10 e 400 e hash inalterado', async () => {
  const existing = existingTechnician();
  const harness = loadUsersRouter({ existing });
  const res = await invoke(harness.router, {
    method: 'put',
    routePath: '/:id',
    params: { id: String(existing.id) },
    body: { password: PASSWORD_10 },
  });

  assert.equal(res.statusCode, 400);
  assert.deepEqual(res.body, { error: 'Password deve ter pelo menos 12 caracteres.' });
  assert.equal(harness.hashes.length, 0);
  assert.equal(harness.revokes.length, 0);
  assert.equal(
    harness.queries.some((item) => /^UPDATE admins/i.test(item.sql)),
    false
  );
  assert.equal(existing.password_hash, EXISTING_HASH);
});

test('P3-3B — PUT sem password deixa hash e sessoes inalterados', async () => {
  const existing = existingTechnician({ name: 'Kept' });
  const harness = loadUsersRouter({ existing });
  const res = await invoke(harness.router, {
    method: 'put',
    routePath: '/:id',
    params: { id: String(existing.id) },
    body: { name: 'Renamed Tech' },
  });

  assert.equal(res.statusCode, 200);
  assert.equal(res.body.user.name, 'Renamed Tech');
  assert.equal(harness.hashes.length, 0);
  assert.equal(harness.revokes.length, 0);
  assert.equal(harness.queries.some((item) => /password_hash =/.test(item.sql)), false);
  assert.equal(existing.password_hash, EXISTING_HASH);
  assert.equal(harness.audits[0].eventType, 'user_updated');
});

test('P3-3B — owner continua 409 e tecnico sem users.manage continua 403', async () => {
  const ownerTarget = existingTechnician({
    id: 1,
    email: 'owner@example.com',
    is_owner: true,
    name: 'Owner',
  });
  const ownerHarness = loadUsersRouter({ existing: ownerTarget });
  const ownerRes = await invoke(ownerHarness.router, {
    method: 'put',
    routePath: '/:id',
    params: { id: '1' },
    body: { name: 'Should Fail' },
  });
  assert.equal(ownerRes.statusCode, 409);
  assert.deepEqual(ownerRes.body, { error: 'Conta owner nao pode ser editada por esta API.' });
  assert.equal(ownerHarness.hashes.length, 0);
  assert.equal(
    ownerHarness.queries.some((item) => /^UPDATE admins/i.test(item.sql)),
    false
  );

  const denied = loadUsersRouter({ admin: technicianAdmin() });
  const deniedRes = await invokeThroughStack(denied.router, {
    method: 'POST',
    url: '/',
    body: {
      email: 'x@example.com',
      name: 'X',
      password: PASSWORD_12,
    },
  });
  assert.equal(deniedRes.statusCode, 403);
  assert.deepEqual(deniedRes.body, { error: 'Sem permissao para esta acao.' });
  assert.equal(denied.hashes.length, 0);
  assert.equal(denied.queries.length, 0);
});

test('P3-3B — autenticacao, bootstrap 12 e /login fora deste ficheiro', () => {
  const usersSrc = fs.readFileSync(path.join(__dirname, 'users.js'), 'utf8');
  const bootstrapSrc = fs.readFileSync(path.join(__dirname, '../../bootstrap-admin.js'), 'utf8');
  const authSrc = fs.readFileSync(path.join(__dirname, 'auth.js'), 'utf8');

  assert.match(usersSrc, /router\.use\(auth\)/);
  assert.match(usersSrc, /requirePermission\('users\.manage'\)/);
  assert.match(usersSrc, /value\.length < 12/);
  assert.match(usersSrc, /Password deve ter pelo menos 12 caracteres\./);
  assert.doesNotMatch(usersSrc, /value\.length < 10/);
  assert.doesNotMatch(usersSrc, /pelo menos 10 caracteres/);

  assert.match(bootstrapSrc, /password\.length < 12/);
  assert.match(bootstrapSrc, /pelo menos 12 caracteres/);

  assert.doesNotMatch(authSrc, /length < 1[02]/);
  assert.doesNotMatch(authSrc, /pelo menos 1[02] caracteres/);
});
