const http = require('node:http');
const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const {
  ADMIN_AUTH_ORIGIN_MESSAGE,
  enforceAdminOrigin,
  isAdminApiPath,
} = require('./admin-surface');

const OFFICIAL_ORIGIN = 'https://license.systemup.inf.br';
const EVIL_ORIGIN = 'https://evil.example';
const ORIGIN_DENIED = ADMIN_AUTH_ORIGIN_MESSAGE;

const ADMIN_MUTATING_ROUTES = [
  { method: 'POST', path: '/api/auth/login' },
  { method: 'POST', path: '/api/auth/login/totp' },
  { method: 'POST', path: '/api/auth/2fa/setup' },
  { method: 'POST', path: '/api/auth/2fa/enable' },
  { method: 'POST', path: '/api/auth/2fa/disable' },
  { method: 'POST', path: '/api/auth/logout' },
  { method: 'POST', path: '/api/licenses' },
  { method: 'PUT', path: '/api/licenses/1' },
  { method: 'POST', path: '/api/licenses/1/revoke' },
  { method: 'POST', path: '/api/licenses/1/renew' },
  { method: 'POST', path: '/api/licenses/1/rebind' },
  { method: 'POST', path: '/api/licenses/1/replace' },
  { method: 'DELETE', path: '/api/licenses/1' },
  { method: 'POST', path: '/api/customers' },
  { method: 'PUT', path: '/api/customers/1' },
  { method: 'DELETE', path: '/api/customers/1' },
  { method: 'POST', path: '/api/users' },
  { method: 'PUT', path: '/api/users/1' },
];

const ADMIN_READ_PATHS = [
  '/api/auth/session',
  '/api/auth/2fa/status',
  '/api/dashboard',
  '/api/licenses',
  '/api/licenses/1',
  '/api/licenses/1/download',
  '/api/customers',
  '/api/customers/1',
  '/api/audit',
  '/api/search',
  '/api/users',
  '/api/users/permissions',
  '/api/installations',
  '/api/installations/1',
];

const PUBLIC_MUTATING_PATHS = [
  '/api/activate',
  '/api/license/check-in',
  '/api/license/install-ping',
];

function probe({ method, path: urlPath, headers = {} }) {
  return new Promise((resolve, reject) => {
    const app = express();
    app.use(enforceAdminOrigin);
    app.use((req, res) => {
      res.status(req.method === 'POST' && req.path === '/api/users' ? 201 : 200).json({ ok: true });
    });

    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      const hasBody = method === 'POST' || method === 'PUT' || method === 'PATCH';
      const req = http.request({
        host: '127.0.0.1',
        port,
        path: urlPath,
        method,
        headers: hasBody
          ? { 'content-type': 'application/json', 'content-length': 2, ...headers }
          : headers,
      }, (res) => {
        let body = '';
        res.on('data', (chunk) => {
          body += chunk;
        });
        res.on('end', () => {
          server.close();
          let parsed = null;
          try {
            parsed = body ? JSON.parse(body) : null;
          } catch {
            parsed = { raw: body };
          }
          resolve({
            status: res.statusCode,
            body: parsed,
          });
        });
      });
      req.on('error', (error) => {
        server.close();
        reject(error);
      });
      req.end(hasBody ? '{}' : undefined);
    });
  });
}

test('P2-2: users and search are admin API paths', () => {
  assert.equal(isAdminApiPath('/api/users'), true);
  assert.equal(isAdminApiPath('/api/users/1'), true);
  assert.equal(isAdminApiPath('/api/users/permissions'), true);
  assert.equal(isAdminApiPath('/api/search'), true);
  assert.equal(isAdminApiPath('/api/search/'), true);
});

test('P2-2: public activate/check-in/health stay outside the admin CSRF surface', () => {
  assert.equal(isAdminApiPath('/api/activate'), false);
  assert.equal(isAdminApiPath('/api/license/check-in'), false);
  assert.equal(isAdminApiPath('/api/license/install-ping'), false);
  assert.equal(isAdminApiPath('/api/health'), false);
  assert.equal(isAdminApiPath('/layer7/blacklists/ut1/current/x'), false);
});

test('BG-162: installations admin API is inside CSRF; install-ping is public', () => {
  assert.equal(isAdminApiPath('/api/installations'), true);
  assert.equal(isAdminApiPath('/api/installations/1'), true);
  assert.equal(isAdminApiPath('/api/license/install-ping'), false);
});

test('P2-2: every mutating admin route is classified as an admin API path', () => {
  for (const route of ADMIN_MUTATING_ROUTES) {
    assert.equal(isAdminApiPath(route.path), true, route.path);
  }
});

test('P2-2 adversarial: POST /api/users with evil Origin is 403', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/users',
    headers: { origin: EVIL_ORIGIN, 'content-type': 'application/json' },
  });
  assert.equal(result.status, 403);
  assert.equal(result.body.error, ORIGIN_DENIED);
});

test('P2-2 adversarial: PUT /api/users/1 with evil Origin is 403', async () => {
  const result = await probe({
    method: 'PUT',
    path: '/api/users/1',
    headers: { origin: EVIL_ORIGIN },
  });
  assert.equal(result.status, 403);
});

test('P2-2 adversarial: state-changing admin routes without Origin or Sec-Fetch-Site fail closed', async () => {
  for (const route of ADMIN_MUTATING_ROUTES) {
    const result = await probe({
      method: route.method,
      path: route.path,
    });
    assert.equal(result.status, 403, `${route.method} ${route.path}`);
  }
});

test('P2-2 adversarial: Sec-Fetch-Site cross-site without Origin is 403', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/licenses/1/revoke',
    headers: { 'sec-fetch-site': 'cross-site' },
  });
  assert.equal(result.status, 403);
});

test('P2-2 adversarial: Sec-Fetch-Site same-site is not enough without Origin', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/auth/login',
    headers: { 'sec-fetch-site': 'same-site' },
  });
  assert.equal(result.status, 403);
});

test('P2-2 compatibility: official portal Origin may mutate admin state', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/users',
    headers: { origin: OFFICIAL_ORIGIN, 'content-type': 'application/json' },
  });
  assert.equal(result.status, 201);
  assert.equal(result.body.ok, true);
});

test('P2-2 compatibility: official Origin still allows login and revoke', async () => {
  const login = await probe({
    method: 'POST',
    path: '/api/auth/login',
    headers: { origin: OFFICIAL_ORIGIN },
  });
  assert.equal(login.status, 200);

  const revoke = await probe({
    method: 'POST',
    path: '/api/licenses/1/revoke',
    headers: { origin: OFFICIAL_ORIGIN },
  });
  assert.equal(revoke.status, 200);
});

test('P2-2 compatibility: Sec-Fetch-Site same-origin allows cookie mutation without Origin', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/auth/logout',
    headers: { 'sec-fetch-site': 'same-origin' },
  });
  assert.equal(result.status, 200);
});

test('P2-2 compatibility: authenticated Bearer APIs may mutate without Origin', async () => {
  const result = await probe({
    method: 'POST',
    path: '/api/licenses/1/revoke',
    headers: { authorization: 'Bearer session-token-from-api-client' },
  });
  assert.equal(result.status, 200);
});

test('P2-2 compatibility: GET admin reads without Origin stay available', async () => {
  for (const adminPath of ADMIN_READ_PATHS) {
    const result = await probe({
      method: 'GET',
      path: adminPath,
    });
    assert.equal(result.status, 200, adminPath);
  }
});

test('P2-2 compatibility: GET admin reads with evil Origin are 403', async () => {
  const result = await probe({
    method: 'GET',
    path: '/api/search',
    headers: { origin: EVIL_ORIGIN },
  });
  assert.equal(result.status, 403);
});

test('P2-2 compatibility: public activate and check-in ignore CSRF admin gate', async () => {
  for (const publicPath of PUBLIC_MUTATING_PATHS) {
    const missing = await probe({
      method: 'POST',
      path: publicPath,
    });
    assert.equal(missing.status, 200, publicPath);

    const evil = await probe({
      method: 'POST',
      path: publicPath,
      headers: { origin: EVIL_ORIGIN },
    });
    assert.equal(evil.status, 200, publicPath);
  }
});
