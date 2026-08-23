const fs = require('node:fs');
const http = require('node:http');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { createAuthMiddleware } = require('./auth-middleware');
const { requirePermission } = require('./require-permission');
const { isAdminApiPath, ADMIN_AUTH_REQUIRED_MESSAGE } = require('./admin-surface');
const { getClientIp } = require('./session');

const SRC_PING = fs.readFileSync(
  path.join(__dirname, 'routes/install-ping.js'),
  'utf8'
);
const SRC_INSTALLATIONS = fs.readFileSync(
  path.join(__dirname, 'routes/installations.js'),
  'utf8'
);
const SRC_INDEX = fs.readFileSync(
  path.join(__dirname, 'index.js'),
  'utf8'
);

function probe(app, { method, path: urlPath, headers = {}, body }) {
  return new Promise((resolve, reject) => {
    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      const payload = body === undefined ? undefined : JSON.stringify(body);
      const req = http.request({
        host: '127.0.0.1',
        port,
        path: urlPath,
        method,
        headers: payload
          ? { 'content-type': 'application/json', 'content-length': Buffer.byteLength(payload), ...headers }
          : headers,
      }, (res) => {
        let raw = '';
        res.on('data', (chunk) => {
          raw += chunk;
        });
        res.on('end', () => {
          server.close();
          let parsed = null;
          try {
            parsed = raw ? JSON.parse(raw) : null;
          } catch {
            parsed = { raw };
          }
          resolve({ status: res.statusCode, body: parsed });
        });
      });
      req.on('error', (error) => {
        server.close();
        reject(error);
      });
      req.end(payload);
    });
  });
}

test('BG-162: install-ping usa getClientIp, rate-limit 10/min e nao le XFF do cliente', () => {
  assert.match(SRC_PING, /getClientIp/);
  assert.match(SRC_PING, /max:\s*10/);
  assert.match(SRC_PING, /windowMs:\s*60 \* 1000/);
  assert.match(SRC_PING, /status:\s*'ok'/);
  assert.doesNotMatch(SRC_PING, /x-forwarded-for/i);
  assert.doesNotMatch(SRC_PING, /X-Forwarded-For/);
});

test('BG-162: GET /api/installations exige sessao + licenses.read', () => {
  assert.match(SRC_INSTALLATIONS, /router\.use\(auth\)/);
  assert.match(SRC_INSTALLATIONS, /requirePermission\('licenses\.read'\)/);
  assert.equal(isAdminApiPath('/api/installations'), true);
  assert.equal(isAdminApiPath('/api/license/install-ping'), false);
});

test('BG-162: index monta install-ping fora da superficie admin', () => {
  assert.match(SRC_INDEX, /installPingRoutes/);
  assert.match(SRC_INDEX, /ensureInstallPingSchema/);
  assert.match(SRC_INDEX, /app\.use\('\/api\/installations'/);
});

test('BG-162: egress_ip autoritativo ignora X-Forwarded-For do cliente', () => {
  const spoofed = {
    ip: '198.51.100.10',
    headers: { 'x-forwarded-for': '203.0.113.99, 198.51.100.10' },
    socket: { remoteAddress: '10.0.0.1' },
  };
  assert.equal(getClientIp(spoofed), '198.51.100.10');
});

test('BG-162: GET /api/installations sem sessao devolve 401', async () => {
  const auth = createAuthMiddleware({
    resolveSession: async () => null,
    clearSessionCookie() {},
    auditAdminEvent: async () => {},
    authRequiredMessage: ADMIN_AUTH_REQUIRED_MESSAGE,
    internalErrorMessage: 'Erro interno.',
  });
  const app = express();
  app.use('/api/installations', auth, requirePermission('licenses.read'), (_req, res) => {
    res.status(200).json({ leaked: true });
  });

  const result = await probe(app, { method: 'GET', path: '/api/installations' });
  assert.equal(result.status, 401);
  assert.equal(result.body.leaked, undefined);
  assert.ok(result.body.error);
});
