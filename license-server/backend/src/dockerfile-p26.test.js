const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const ROOT = path.join(__dirname, '../..');
const BACKEND_DOCKERFILE = path.join(ROOT, 'backend/Dockerfile');
const FRONTEND_DOCKERFILE = path.join(ROOT, 'frontend/Dockerfile');
const BACKEND_DOCKERIGNORE = path.join(ROOT, 'backend/.dockerignore');
const FRONTEND_DOCKERIGNORE = path.join(ROOT, 'frontend/.dockerignore');
const COMPOSE = path.join(ROOT, 'docker-compose.yml');

function load(filePath) {
  return fs.readFileSync(filePath, 'utf8');
}

function activeLines(text) {
  return text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line && !line.startsWith('#'));
}

test('P2-6A: backend Dockerfile runs CMD as USER node after the last COPY', () => {
  const lines = activeLines(load(BACKEND_DOCKERFILE));
  const userIdx = lines.lastIndexOf('USER node');
  const copyIdx = lines.findLastIndex((line) => line.startsWith('COPY '));
  const cmdIdx = lines.findIndex((line) => line.startsWith('CMD '));

  assert.notEqual(userIdx, -1, 'backend must have USER node');
  assert.notEqual(copyIdx, -1, 'backend must COPY the app');
  assert.notEqual(cmdIdx, -1, 'backend must have CMD');
  assert.ok(userIdx > copyIdx, 'USER node must follow the last COPY');
  assert.ok(userIdx < cmdIdx, 'USER node must precede CMD');
  assert.equal(lines.some((line) => /^USER\s+root$/i.test(line)), false);
});

test('P2-6A: frontend Dockerfile does not use USER node (nginx listen 80)', () => {
  const text = load(FRONTEND_DOCKERFILE);
  assert.equal(/\bUSER\s+node\b/.test(text), false);
  assert.match(text, /listen 80;/);
});

test('P2-6A: backend and frontend .dockerignore exclude .env and context junk', () => {
  for (const filePath of [BACKEND_DOCKERIGNORE, FRONTEND_DOCKERIGNORE]) {
    const patterns = activeLines(load(filePath));
    for (const required of ['.env', '.env.*', 'node_modules', '.git']) {
      assert.ok(
        patterns.includes(required),
        `${path.relative(ROOT, filePath)} must ignore ${required}`
      );
    }
  }
});

test('P2-6B: db healthcheck uses in-container pg_isready; api waits for healthy', () => {
  const compose = load(COMPOSE);
  const healthchecks = compose.match(/^\s+healthcheck\s*:/gm) || [];

  assert.equal(healthchecks.length, 1, 'only db may declare healthcheck');
  assert.match(compose, /pg_isready -U \$\$POSTGRES_USER -d \$\$POSTGRES_DB/);
  assert.match(compose, /condition:\s*service_healthy/);
  assert.match(
    compose,
    /depends_on:\n {6}db:\n {8}condition:\s*service_healthy\n/
  );
  assert.equal(/depends_on:\n {6}- db\n/.test(compose), false);
  assert.match(compose, /image:\s*postgres:17-alpine/);
  assert.match(compose, /image:\s*nginx:alpine/);
  assert.equal(/\bUSER\s+node\b/.test(compose), false);
});
