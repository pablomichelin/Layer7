const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const { requireSecureSessionRequest } = require('./session');

const ROOT = path.join(__dirname, '../..');
const COMPOSE = path.join(ROOT, 'docker-compose.yml');
const ENV_EXAMPLE = path.join(ROOT, '.env.example');
const NGINX = path.join(ROOT, 'nginx/nginx.conf');

function load(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function activeNginxLines() {
  return load(NGINX)
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));
}

test('P1-9 proof: HEAD compose default bind stays loopback 127.0.0.1:8445', () => {
  const compose = load(COMPOSE);
  assert.match(
    compose,
    /"\$\{LICENSE_SERVER_ORIGIN_BIND_IP:-127\.0\.0\.1\}:\$\{LICENSE_SERVER_ORIGIN_PORT:-8445\}:80"/
  );
  assert.equal(compose.includes('0.0.0.0:8445'), false);
  assert.equal(compose.includes(':-0.0.0.0}'), false);
});

test('P1-9 proof: only nginx publishes a host port; api/db/web stay internal', () => {
  const compose = load(COMPOSE);
  const services = compose.split(/^\s{2}[a-z]+:/m).slice(1);
  assert.ok(services.length >= 4, 'expected db/api/web/nginx services');
  const published = [...compose.matchAll(/^\s{4}ports:\s*$/gm)];
  assert.equal(published.length, 1, 'only nginx may publish ports');
  assert.match(compose, /container_name: layer7-license-nginx[\s\S]*ports:/);
});

test('P1-9 proof: .env.example documents loopback origin bind, not 0.0.0.0', () => {
  const example = load(ENV_EXAMPLE);
  assert.match(example, /^LICENSE_SERVER_ORIGIN_BIND_IP=127\.0\.0\.1$/m);
  assert.equal(example.includes('LICENSE_SERVER_ORIGIN_BIND_IP=0.0.0.0'), false);
});

test('P1-9 proof: unknown Host on origin HTTP is default_server 444', () => {
  const lines = activeNginxLines();
  const defaultIdx = lines.findIndex((line) => line === 'listen 80 default_server;');
  assert.ok(defaultIdx >= 0);
  const serverSlice = lines.slice(defaultIdx, defaultIdx + 8);
  assert.equal(serverSlice.includes('return 444;'), true);
  assert.equal(serverSlice.some((line) => line.startsWith('proxy_pass')), false);
});

test('P1-9 residual after P2-3 is F2.1, not an open origin-IP login', () => {
  const production = { NODE_ENV: 'production' };

  assert.equal(requireSecureSessionRequest({
    secure: false,
    headers: {
      host: 'license.systemup.inf.br',
      'x-forwarded-host': 'license.systemup.inf.br',
      'x-forwarded-proto': 'http',
    },
  }, production), true);

  assert.equal(requireSecureSessionRequest({
    secure: true,
    headers: {
      host: '127.0.0.1:8445',
      'x-forwarded-proto': 'https',
    },
  }, production), false);

  assert.equal(requireSecureSessionRequest({
    secure: true,
    headers: {
      host: '192.168.100.244:8445',
      'x-forwarded-proto': 'https',
    },
  }, production), false);
});
