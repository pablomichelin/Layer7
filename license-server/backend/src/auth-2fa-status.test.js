const http = require('node:http');
const { spawnSync } = require('node:child_process');
const test = require('node:test');
const assert = require('node:assert/strict');
const express = require('express');
const { ADMIN_INTERNAL_ERROR_MESSAGE } = require('./admin-surface');

const STATUS_PATH = '/api/auth/2fa/status';
const SENSITIVE_MARKERS = [
  'owner@example.com',
  'tech@example.com',
  'layer7_admin_session',
  'totp_secret',
  'password_hash',
];

function ownerAdmin() {
  return {
    id: 7,
    email: 'owner@example.com',
    name: 'Owner',
    is_owner: true,
    is_active: true,
    permissions: ['*'],
  };
}

function technicianWithoutSelf() {
  return {
    id: 8,
    email: 'tech@example.com',
    name: 'Tech',
    is_owner: false,
    is_active: true,
    permissions: ['licenses.read'],
  };
}

function loadStatusRouter({ admin = ownerAdmin(), query } = {}) {
  const dbPath = require.resolve('./db');
  const authPath = require.resolve('./auth');
  const authRoutePath = require.resolve('./routes/auth');

  require.cache[dbPath] = {
    id: dbPath,
    filename: dbPath,
    loaded: true,
    exports: {
      query: query || (async () => ({ rows: [{ totp_enabled: true }] })),
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

  delete require.cache[authRoutePath];
  return require('./routes/auth');
}

function listenApp(app) {
  return new Promise((resolve, reject) => {
    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      resolve({ server, port });
    });
    server.on('error', reject);
  });
}

function httpGet(port, urlPath) {
  return new Promise((resolve, reject) => {
    const req = http.request({
      host: '127.0.0.1',
      port,
      path: urlPath,
      method: 'GET',
    }, (res) => {
      let body = '';
      res.on('data', (chunk) => {
        body += chunk;
      });
      res.on('end', () => {
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
    req.on('error', reject);
    req.end();
  });
}

function trackUnhandledRejections() {
  const seen = [];
  const onUnhandled = (reason) => {
    seen.push(reason);
  };
  process.on('unhandledRejection', onUnhandled);
  return {
    seen,
    stop() {
      process.removeListener('unhandledRejection', onUnhandled);
    },
  };
}

function captureConsoleError(run) {
  const lines = [];
  const original = console.error;
  console.error = (...args) => {
    lines.push(args.map((value) => String(value)).join(' '));
  };
  return Promise.resolve()
    .then(run)
    .finally(() => {
      console.error = original;
    })
    .then((result) => ({ result, lines }));
}

test('P3-4: o harness HTTP corre em Express 4 sem wrapper global', () => {
  const version = require('express/package.json').version;
  assert.match(version, /^4\./);
  assert.equal(typeof express, 'function');
});

test('P3-4 fail-before: Express 4 nao converte rejeicao de pool.query em 500 JSON', () => {
  const script = `
    const http = require('node:http');
    const express = require(${JSON.stringify(require.resolve('express'))});
    const unhandled = [];
    process.on('unhandledRejection', (reason) => {
      unhandled.push(String(reason && reason.message ? reason.message : reason));
    });
    const app = express();
    app.get('/api/auth/2fa/status', async (_req, res) => {
      const result = await Promise.reject(new Error('simulated pool failure'));
      return res.json({ totp_enabled: Boolean(result.rows[0]?.totp_enabled) });
    });
    const server = app.listen(0, '127.0.0.1', () => {
      const { port } = server.address();
      const req = http.request({
        host: '127.0.0.1',
        port,
        path: '/api/auth/2fa/status',
        method: 'GET',
      }, (res) => {
        let body = '';
        res.on('data', (chunk) => { body += chunk; });
        res.on('end', () => {
          clearTimeout(timer);
          server.close();
          process.stdout.write(JSON.stringify({
            http_500_json: res.statusCode === 500 && body.includes('Erro interno.'),
            status: res.statusCode,
            unhandled,
            process_alive: true,
          }));
          process.exit(2);
        });
      });
      const timer = setTimeout(() => {
        req.destroy();
        server.close();
        process.stdout.write(JSON.stringify({
          http_500_json: false,
          status: null,
          unhandled,
          process_alive: true,
        }));
        process.exit(0);
      }, 400);
      req.end();
    });
  `;

  const child = spawnSync(process.execPath, ['-e', script], {
    encoding: 'utf8',
    timeout: 5000,
  });
  assert.equal(child.error, undefined, child.stderr);
  const result = JSON.parse(child.stdout);
  assert.equal(result.http_500_json, false);
  assert.equal(result.status, null);
  assert.ok(result.unhandled.includes('simulated pool failure'));
  assert.equal(result.process_alive, true);
  assert.equal(child.status, 0);
});

test('P3-4 pass-after: pool rejeitado devolve 500 JSON, sem unhandledRejection, e o GET seguinte fica 200', async () => {
  let calls = 0;
  const router = loadStatusRouter({
    async query() {
      calls += 1;
      if (calls === 1) {
        throw new Error('simulated pool failure');
      }
      return { rows: [{ totp_enabled: true }] };
    },
  });

  const app = express();
  app.use('/api/auth', router);
  const { server, port } = await listenApp(app);
  const tracker = trackUnhandledRejections();

  try {
    const { result: first, lines } = await captureConsoleError(() => httpGet(port, STATUS_PATH));
    assert.equal(first.status, 500);
    assert.deepEqual(first.body, { error: ADMIN_INTERNAL_ERROR_MESSAGE });
    assert.equal(ADMIN_INTERNAL_ERROR_MESSAGE, 'Erro interno.');
    assert.equal(tracker.seen.length, 0);
    assert.ok(lines.some((line) => line.includes('[AUTH] 2FA status error:')));
    assert.ok(lines.some((line) => line.includes('simulated pool failure')));
    for (const marker of SENSITIVE_MARKERS) {
      assert.equal(lines.some((line) => line.includes(marker)), false, marker);
    }

    const second = await httpGet(port, STATUS_PATH);
    assert.equal(second.status, 200);
    assert.deepEqual(second.body, { totp_enabled: true });
    assert.equal(tracker.seen.length, 0);
    assert.equal(calls, 2);
    assert.equal(process.exitCode, undefined);
  } finally {
    tracker.stop();
    await new Promise((resolve) => server.close(resolve));
  }
});

test('P3-4: GET /2fa/status saudavel continua 200 com totp_enabled true ou false', async () => {
  const router = loadStatusRouter({
    async query() {
      return { rows: [{ totp_enabled: false }] };
    },
  });
  const app = express();
  app.use('/api/auth', router);
  const { server, port } = await listenApp(app);
  const tracker = trackUnhandledRejections();

  try {
    const enabled = await httpGet(port, STATUS_PATH);
    assert.equal(enabled.status, 200);
    assert.deepEqual(enabled.body, { totp_enabled: false });

    const routerTrue = loadStatusRouter({
      async query() {
        return { rows: [{ totp_enabled: true }] };
      },
    });
    const appTrue = express();
    appTrue.use('/api/auth', routerTrue);
    const healthy = await listenApp(appTrue);
    try {
      const result = await httpGet(healthy.port, STATUS_PATH);
      assert.equal(result.status, 200);
      assert.deepEqual(result.body, { totp_enabled: true });
    } finally {
      await new Promise((resolve) => healthy.server.close(resolve));
    }
    assert.equal(tracker.seen.length, 0);
  } finally {
    tracker.stop();
    await new Promise((resolve) => server.close(resolve));
  }
});

test('P3-4: GET /2fa/status sem sessao continua 401', async () => {
  const router = loadStatusRouter({ admin: null });
  const app = express();
  app.use('/api/auth', router);
  const { server, port } = await listenApp(app);

  try {
    const result = await httpGet(port, STATUS_PATH);
    assert.equal(result.status, 401);
    assert.equal(result.body.error, 'Autenticacao obrigatoria.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
});

test('P3-4: GET /2fa/status sem security.self continua 403', async () => {
  const router = loadStatusRouter({ admin: technicianWithoutSelf() });
  const app = express();
  app.use('/api/auth', router);
  const { server, port } = await listenApp(app);

  try {
    const result = await httpGet(port, STATUS_PATH);
    assert.equal(result.status, 403);
    assert.equal(result.body.error, 'Sem permissao para esta acao.');
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
});
