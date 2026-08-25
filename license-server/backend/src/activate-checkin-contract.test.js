const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const SRC_ACTIVATE = fs.readFileSync(
  path.join(__dirname, 'routes/activate.js'),
  'utf8'
);
const SRC_CHECKIN = fs.readFileSync(
  path.join(__dirname, 'routes/check-in.js'),
  'utf8'
);
const SRC_LICENSES = fs.readFileSync(
  path.join(__dirname, 'routes/licenses.js'),
  'utf8'
);

test('BG-166 / LS-1: activate usa getClientIp e nao le XFF do cliente', () => {
  assert.match(SRC_ACTIVATE, /getClientIp/);
  assert.doesNotMatch(SRC_ACTIVATE, /x-forwarded-for/i);
  assert.doesNotMatch(SRC_ACTIVATE, /x-real-ip/i);
});

test('BG-166 / LS-2: check-in usa getClientIp e nao le XFF do cliente', () => {
  assert.match(SRC_CHECKIN, /getClientIp/);
  assert.doesNotMatch(SRC_CHECKIN, /x-forwarded-for/i);
  assert.doesNotMatch(SRC_CHECKIN, /x-real-ip/i);
});

test('BG-166 / LS-5: activate emite features via normalizeFeatures', () => {
  assert.match(SRC_ACTIVATE, /normalizeFeatures\(license\.features/);
  assert.doesNotMatch(SRC_ACTIVATE, /features:\s*license\.features\s*\|\|/);
});

test('BG-166 / LS-6: download .lic emite features via normalizeFeatures', () => {
  assert.match(SRC_LICENSES, /normalizeFeatures\(license\.features/);
  assert.doesNotMatch(SRC_LICENSES, /features:\s*license\.features\s*\|\|/);
});
