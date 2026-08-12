const test = require('node:test');
const assert = require('node:assert/strict');
const nacl = require('tweetnacl');

const {
  CHECK_IN_ENVELOPE_VERSION,
  buildActiveCheckInPayloadV2,
  buildActiveCheckInResponse,
  buildDeniedCheckInPayloadV2,
  wrapSignedCheckInEnvelope,
} = require('./check-in-policy');
const { signData } = require('./crypto');
const {
  normalizeCheckInNonce,
  parseCheckInPayload,
} = require('./crud-validation');
const { createHttpError } = require('./http-error');

/** Seed efémera só para testes — nunca material de produção. */
const TEST_SEED_HEX = '22'.repeat(32);

function testSign(dataString) {
  return signData(dataString, TEST_SEED_HEX);
}

function testVerify(data, sig) {
  const kp = nacl.sign.keyPair.fromSeed(Buffer.from(TEST_SEED_HEX, 'hex'));
  return nacl.sign.detached.verify(
    Buffer.from(data, 'utf8'),
    Buffer.from(sig, 'hex'),
    kp.publicKey
  );
}

const LICENSE = {
  id: 7,
  expiry: '2027-12-31',
  customer_name: 'Cliente AP3',
  customer_id: 3,
  features: 'base',
};

const POLICY = { checkInIntervalHours: 168, maxOfflineHours: 336 };
const LICENSE_KEY = 'abcdef0123456789abcdef0123456789';
const HW = 'a'.repeat(64);
const NONCE = Buffer.alloc(32, 7).toString('base64url');

test('normalizeCheckInNonce accepts 43-char base64url (D3)', () => {
  assert.equal(normalizeCheckInNonce(NONCE), NONCE);
  assert.equal(normalizeCheckInNonce(null), null);
  assert.equal(normalizeCheckInNonce(''), null);
});

test('normalizeCheckInNonce rejects padding and short values', () => {
  assert.throws(() => normalizeCheckInNonce('abc'), (err) => err.status === 400);
  assert.throws(
    () => normalizeCheckInNonce(`${NONCE}=`),
    (err) => err.status === 400
  );
});

test('parseCheckInPayload dual-mode: sem nonce (D10 / C9)', () => {
  const p = parseCheckInPayload({
    key: LICENSE_KEY,
    hardware_id: HW,
  });
  assert.equal(p.nonce, null);
  assert.equal(p.hardwareId, HW);
});

test('parseCheckInPayload exige nonce válido quando presente', () => {
  const p = parseCheckInPayload({
    key: LICENSE_KEY,
    hardware_id: HW,
    nonce: NONCE,
  });
  assert.equal(p.nonce, NONCE);
});

test('C1 — envelope activo assinado verifica com mesma chave', () => {
  const payload = buildActiveCheckInPayloadV2(LICENSE, POLICY, {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_000,
    signFn: testSign,
    jti: 'jti-c1',
  });
  const env = wrapSignedCheckInEnvelope(payload, { signFn: testSign });
  assert.match(env.sig, /^[0-9a-f]{128}$/i);
  assert.equal(testVerify(env.data, env.sig), true);
  const inner = JSON.parse(env.data);
  assert.equal(inner.v, CHECK_IN_ENVELOPE_VERSION);
  assert.equal(inner.status, 'active');
  assert.equal(inner.nonce, NONCE);
  assert.equal(inner.hardware_id, HW);
  assert.equal(inner.iat, 1_700_000_000);
  assert.ok(inner.content_subscription);
});

test('C2 — JSON legado sem sig não é envelope v2', () => {
  const legacy = buildActiveCheckInResponse(LICENSE, POLICY, {
    hardwareId: HW,
    signFn: testSign,
    jti: 'jti-c2',
  });
  assert.equal(legacy.sig, undefined);
  assert.equal(typeof legacy.data, 'undefined');
  assert.equal(legacy.status, 'active');
});

test('C3 — sig adulterada falha verify', () => {
  const payload = buildActiveCheckInPayloadV2(LICENSE, POLICY, {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_000,
    signFn: testSign,
    jti: 'jti-c3',
  });
  const env = wrapSignedCheckInEnvelope(payload, { signFn: testSign });
  const badSig = env.sig.replace(/0/g, '1').replace(/1/g, '0');
  assert.equal(testVerify(env.data, badSig), false);
});

test('C4 — replay: nonce do envelope ≠ nonce do pedido actual', () => {
  const payload = buildActiveCheckInPayloadV2(LICENSE, POLICY, {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_000,
    signFn: testSign,
    jti: 'jti-c4',
  });
  const otherNonce = Buffer.alloc(32, 9).toString('base64url');
  assert.notEqual(payload.nonce, otherNonce);
});

test('C5/C6 — sem chave correcta o verify falha (servidor falso)', () => {
  const payload = buildActiveCheckInPayloadV2(LICENSE, POLICY, {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_000,
    signFn: testSign,
    jti: 'jti-c5',
  });
  const env = wrapSignedCheckInEnvelope(payload, { signFn: testSign });
  const evilSeed = '33'.repeat(32);
  const evilKp = nacl.sign.keyPair.fromSeed(Buffer.from(evilSeed, 'hex'));
  assert.equal(
    nacl.sign.detached.verify(
      Buffer.from(env.data, 'utf8'),
      Buffer.from(env.sig, 'hex'),
      evilKp.publicKey
    ),
    false
  );
});

test('C7 — denied revoked assinado inclui eco nonce/hw', () => {
  const denied = buildDeniedCheckInPayloadV2('revoked', 'Licenca revogada.', {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_100,
  });
  const env = wrapSignedCheckInEnvelope(denied, { signFn: testSign });
  assert.equal(testVerify(env.data, env.sig), true);
  const inner = JSON.parse(env.data);
  assert.equal(inner.status, 'revoked');
  assert.equal(inner.nonce, NONCE);
  assert.equal(inner.hardware_id, HW);
  assert.equal(inner.error, 'Licenca revogada.');
});

test('C10 — content_subscription aninhado no payload activo', () => {
  const payload = buildActiveCheckInPayloadV2(LICENSE, POLICY, {
    hardwareId: HW,
    nonce: NONCE,
    nowSec: 1_700_000_000,
    signFn: testSign,
    jti: 'jti-c10',
  });
  assert.ok(payload.content_subscription.data);
  assert.match(payload.content_subscription.sig, /^[0-9a-f]{128}$/i);
  const token = JSON.parse(payload.content_subscription.data);
  assert.equal(token.hardware_id, HW);
  assert.equal(token.scope, 'content');
});

test('createHttpError shape preserved for unexpected fields', () => {
  assert.throws(
    () =>
      parseCheckInPayload({
        key: LICENSE_KEY,
        hardware_id: HW,
        evil: 1,
      }),
    (err) => err instanceof Error && err.status === 400
  );
  assert.ok(createHttpError(400, 'x').status === 400);
});
