const test = require('node:test');
const assert = require('node:assert/strict');
const nacl = require('tweetnacl');

const {
  CONTENT_SUBSCRIPTION_TTL_SEC,
  buildContentSubscriptionEnvelope,
  verifyContentSubscriptionEnvelope,
} = require('./content-subscription');
const { signData } = require('./crypto');
const {
  buildActiveCheckInResponse,
  buildDeniedCheckInResponse,
} = require('./check-in-policy');

/** Seed efémera só para testes — nunca material de produção (GA4.13 / N8). */
const TEST_SEED_HEX = '11'.repeat(32);

function testSign(dataString) {
  return signData(dataString, TEST_SEED_HEX);
}

test('buildContentSubscriptionEnvelope signs Ed25519 envelope (GA4.2)', () => {
  const env = buildContentSubscriptionEnvelope({
    hardwareId: 'hw-appliance-a',
    licenseId: 42,
    customerId: 7,
    features: 'base,identity',
    nowSec: 1_700_000_000,
    jti: 'jti-fixed-1',
    signFn: testSign,
  });

  assert.match(env.sig, /^[0-9a-f]{128}$/);
  const payload = JSON.parse(env.data);
  assert.equal(payload.v, 1);
  assert.equal(payload.hardware_id, 'hw-appliance-a');
  assert.equal(payload.license_id, 42);
  assert.equal(payload.scope, 'content');
  assert.equal(payload.iat, 1_700_000_000);
  assert.equal(payload.exp, 1_700_000_000 + CONTENT_SUBSCRIPTION_TTL_SEC);
  assert.equal(payload.jti, 'jti-fixed-1');
  assert.equal(payload.customer_id, 7);

  const verified = verifyContentSubscriptionEnvelope(env, TEST_SEED_HEX);
  assert.equal(verified.ok, true);
  assert.equal(verified.payload.hardware_id, 'hw-appliance-a');
});

test('token hardware_id binding — different appliance fails client check (GA4.3)', () => {
  const env = buildContentSubscriptionEnvelope({
    hardwareId: 'hw-a',
    licenseId: 1,
    nowSec: 1_700_000_000,
    signFn: testSign,
  });
  const verified = verifyContentSubscriptionEnvelope(env, TEST_SEED_HEX);
  assert.equal(verified.ok, true);
  assert.notEqual(verified.payload.hardware_id, 'hw-b');
});

test('tampered data fails signature verify', () => {
  const env = buildContentSubscriptionEnvelope({
    hardwareId: 'hw-a',
    licenseId: 1,
    nowSec: 1_700_000_000,
    signFn: testSign,
  });
  const bad = { data: env.data.replace('hw-a', 'hw-x'), sig: env.sig };
  const verified = verifyContentSubscriptionEnvelope(bad, TEST_SEED_HEX);
  assert.equal(verified.ok, false);
  assert.equal(verified.reason, 'sig_verify');
});

test('buildActiveCheckInResponse includes content_subscription when hardwareId set', () => {
  const response = buildActiveCheckInResponse(
    {
      id: 99,
      expiry: '2027-12-31',
      customer_name: 'Cliente',
      customer_id: 3,
      features: 'base',
    },
    { checkInIntervalHours: 168, maxOfflineHours: 336 },
    {
      hardwareId: 'hw-99',
      nowSec: 1_700_000_000,
      jti: 'jti-99',
      signFn: testSign,
    }
  );

  assert.equal(response.status, 'active');
  assert.ok(response.content_subscription);
  assert.ok(response.content_subscription.data);
  assert.ok(response.content_subscription.sig);
  const payload = JSON.parse(response.content_subscription.data);
  assert.equal(payload.hardware_id, 'hw-99');
  assert.equal(payload.license_id, 99);
});

test('buildActiveCheckInResponse without hardwareId omits token (unit compat)', () => {
  const response = buildActiveCheckInResponse(
    { expiry: '2027-12-31', customer_name: 'X' },
    { checkInIntervalHours: 168, maxOfflineHours: 336 }
  );
  assert.equal(response.content_subscription, undefined);
});

test('denied check-in never carries content_subscription (GA4.2)', () => {
  const revoked = buildDeniedCheckInResponse('revoked', 'Licenca revogada.');
  const expired = buildDeniedCheckInResponse('expired', 'Licenca expirada.');
  assert.equal(revoked.content_subscription, undefined);
  assert.equal(expired.content_subscription, undefined);
  assert.equal(Object.prototype.hasOwnProperty.call(revoked, 'content_subscription'), false);
});

test('signData + nacl roundtrip uses seed (no prod secrets in repo)', () => {
  const msg = 'hello-30.9';
  const sig = signData(msg, TEST_SEED_HEX);
  const kp = nacl.sign.keyPair.fromSeed(Buffer.from(TEST_SEED_HEX, 'hex'));
  assert.equal(
    nacl.sign.detached.verify(
      Buffer.from(msg, 'utf8'),
      Buffer.from(sig, 'hex'),
      kp.publicKey
    ),
    true
  );
});
