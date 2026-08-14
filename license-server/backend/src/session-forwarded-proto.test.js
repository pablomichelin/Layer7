const http = require('node:http');
const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const {
  requireSecureSessionRequest,
} = require('./session');

const CHANNEL_ERROR = 'Canal administrativo invalido.';

function probeLoginChannel({ headers, env }) {
  return new Promise((resolve, reject) => {
    const app = express();
    app.set('trust proxy', 1);
    app.post('/api/auth/login', (req, res) => {
      if (!requireSecureSessionRequest(req, env)) {
        return res.status(400).json({ error: CHANNEL_ERROR });
      }
      return res.status(200).json({ ok: true });
    });

    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      const req = http.request({
        host: '127.0.0.1',
        port,
        path: '/api/auth/login',
        method: 'POST',
        headers,
      }, (res) => {
        let body = '';
        res.on('data', (chunk) => {
          body += chunk;
        });
        res.on('end', () => {
          server.close();
          resolve({
            status: res.statusCode,
            body: JSON.parse(body),
          });
        });
      });
      req.on('error', (error) => {
        server.close();
        reject(error);
      });
      req.end('{}');
    });
  });
}

test('P2-3 adversarial: HTTP + X-Forwarded-Proto https on origin host is not secure', () => {
  const production = { NODE_ENV: 'production' };
  const spoofed = {
    secure: true,
    headers: {
      host: '127.0.0.1:8445',
      'x-forwarded-proto': 'https',
    },
  };

  assert.equal(requireSecureSessionRequest(spoofed, production), false);
  assert.equal(requireSecureSessionRequest({
    secure: true,
    headers: { host: '192.168.100.244:8445', 'x-forwarded-proto': 'https' },
  }, production), false);
});

test('P2-3 official F2.1 host stays accepted after origin $scheme is http', () => {
  const production = { NODE_ENV: 'production' };
  assert.equal(requireSecureSessionRequest({
    secure: false,
    headers: {
      host: 'license.systemup.inf.br',
      'x-forwarded-proto': 'http',
      'x-forwarded-host': 'license.systemup.inf.br',
    },
  }, production), true);
});

test('P2-3 comma-separated X-Forwarded-Host is fail-closed', () => {
  assert.equal(requireSecureSessionRequest({
    secure: true,
    headers: {
      host: '127.0.0.1',
      'x-forwarded-host': 'evil.test, license.systemup.inf.br',
      'x-forwarded-proto': 'https',
    },
  }, { NODE_ENV: 'production' }), false);
});

test('P2-3 localhost is only a non-production exception', () => {
  const local = {
    secure: true,
    headers: { host: '127.0.0.1', 'x-forwarded-proto': 'https' },
  };
  assert.equal(requireSecureSessionRequest(local, { NODE_ENV: 'production' }), false);
  assert.equal(requireSecureSessionRequest(local, {}), false);
  assert.equal(requireSecureSessionRequest(local, { NODE_ENV: 'test' }), true);
  assert.equal(requireSecureSessionRequest(local, { NODE_ENV: 'development' }), true);
});

test('P2-3 HTTP + header https → login 400 on origin HTTP', async () => {
  const result = await probeLoginChannel({
    env: { NODE_ENV: 'production' },
    headers: {
      Host: '127.0.0.1:8445',
      'X-Forwarded-Proto': 'https',
      'Content-Type': 'application/json',
    },
  });

  assert.equal(result.status, 400);
  assert.deepEqual(result.body, { error: CHANNEL_ERROR });
});

test('P2-3 official host still logs in after proto is the origin $scheme', async () => {
  const result = await probeLoginChannel({
    env: { NODE_ENV: 'production' },
    headers: {
      Host: 'license.systemup.inf.br',
      'X-Forwarded-Host': 'license.systemup.inf.br',
      'X-Forwarded-Proto': 'http',
      'Content-Type': 'application/json',
    },
  });

  assert.equal(result.status, 200);
  assert.deepEqual(result.body, { ok: true });
});
