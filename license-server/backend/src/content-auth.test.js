const test = require('node:test');
const assert = require('node:assert/strict');

const { buildContentSubscriptionEnvelope } = require('./content-subscription');
const { signData } = require('./crypto');
const {
  decodeBase64UrlToUtf8,
  verifyContentBearerToken,
} = require('./content-auth');

const TEST_SEED_HEX = '22'.repeat(32);

function testSign(dataString) {
  return signData(dataString, TEST_SEED_HEX);
}

function toBearer(envelope) {
  return Buffer.from(JSON.stringify(envelope), 'utf8')
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');
}

test('verifyContentBearerToken accepts valid base64url envelope', () => {
  const now = 1_700_000_000;
  const env = buildContentSubscriptionEnvelope({
    hardwareId: 'hw-1',
    licenseId: 9,
    nowSec: now,
    signFn: testSign,
  });
  const result = verifyContentBearerToken(toBearer(env), {
    nowSec: now + 60,
    seedHex: TEST_SEED_HEX,
  });
  assert.equal(result.ok, true);
  assert.equal(result.payload.hardware_id, 'hw-1');
});

test('verifyContentBearerToken rejects missing token', () => {
  const result = verifyContentBearerToken('', { seedHex: TEST_SEED_HEX });
  assert.equal(result.ok, false);
  assert.equal(result.reason, 'missing');
});

test('verifyContentBearerToken rejects expired token (D3)', () => {
  const now = 1_700_000_000;
  const env = buildContentSubscriptionEnvelope({
    hardwareId: 'hw-1',
    licenseId: 9,
    nowSec: now,
    ttlSec: 100,
    signFn: testSign,
  });
  const result = verifyContentBearerToken(toBearer(env), {
    nowSec: now + 100 + 86400 + 1,
    seedHex: TEST_SEED_HEX,
  });
  assert.equal(result.ok, false);
  assert.equal(result.reason, 'expired');
});

test('decodeBase64UrlToUtf8 roundtrip', () => {
  const raw = '{"data":"x","sig":"y"}';
  const b64 = Buffer.from(raw, 'utf8').toString('base64url');
  assert.equal(decodeBase64UrlToUtf8(b64), raw);
});
