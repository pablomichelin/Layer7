const crypto = require('crypto');
const { signData } = require('./crypto');
const { readSecret } = require('./secret-config');

/** Contrato 30.8 D1 — validade nominal. */
const CONTENT_SUBSCRIPTION_TTL_SEC = 2592000;
/** Contrato 30.8 D2 — skew no verificador (documentado; emissão não aplica). */
const CONTENT_SUBSCRIPTION_SKEW_SEC = 86400;
const CONTENT_SUBSCRIPTION_SCOPE = 'content';
const CONTENT_SUBSCRIPTION_VERSION = 1;

function defaultSign(dataString) {
  const privateKeyHex = readSecret('ED25519_PRIVATE_KEY', {
    required: true,
    emptyMessage: 'ED25519_PRIVATE_KEY nao configurada no ambiente',
  });
  return signData(dataString, privateKeyHex);
}

/**
 * Emite envelope content_subscription (contrato 30.8).
 * signFn opcional para testes (GA4.13 — sem segredos no git).
 */
function buildContentSubscriptionEnvelope({
  hardwareId,
  licenseId,
  customerId = null,
  features = null,
  nowSec = null,
  ttlSec = CONTENT_SUBSCRIPTION_TTL_SEC,
  signFn = defaultSign,
  jti = null,
}) {
  const hw = String(hardwareId || '').trim();
  if (!hw) {
    throw new Error('hardware_id obrigatorio para content_subscription');
  }
  if (licenseId === undefined || licenseId === null || licenseId === '') {
    throw new Error('license_id obrigatorio para content_subscription');
  }

  const iat = Number.isFinite(nowSec) ? Math.trunc(nowSec) : Math.floor(Date.now() / 1000);
  const ttl = Number.isFinite(ttlSec) && ttlSec > 0 ? Math.trunc(ttlSec) : CONTENT_SUBSCRIPTION_TTL_SEC;
  const exp = iat + ttl;

  const payloadObj = {
    v: CONTENT_SUBSCRIPTION_VERSION,
    hardware_id: hw,
    license_id: licenseId,
    scope: CONTENT_SUBSCRIPTION_SCOPE,
    iat,
    exp,
    jti: jti || crypto.randomUUID(),
  };
  if (customerId !== null && customerId !== undefined && customerId !== '') {
    payloadObj.customer_id = customerId;
  }
  if (typeof features === 'string' && features !== '') {
    payloadObj.features = features;
  }

  const data = JSON.stringify(payloadObj);
  const sig = signFn(data);
  if (typeof sig !== 'string' || !/^[0-9a-fA-F]{128}$/.test(sig)) {
    throw new Error('assinatura content_subscription invalida');
  }

  return { data, sig };
}

function verifyContentSubscriptionEnvelope(envelope, publicKeySeedOrVerifyFn) {
  if (!envelope || typeof envelope.data !== 'string' || typeof envelope.sig !== 'string') {
    return { ok: false, reason: 'envelope' };
  }
  if (!/^[0-9a-fA-F]{128}$/.test(envelope.sig)) {
    return { ok: false, reason: 'sig' };
  }

  let payload;
  try {
    payload = JSON.parse(envelope.data);
  } catch (_err) {
    return { ok: false, reason: 'json' };
  }

  if (payload.v !== CONTENT_SUBSCRIPTION_VERSION) {
    return { ok: false, reason: 'version' };
  }
  if (payload.scope !== CONTENT_SUBSCRIPTION_SCOPE) {
    return { ok: false, reason: 'scope' };
  }
  if (!payload.hardware_id || payload.license_id === undefined) {
    return { ok: false, reason: 'fields' };
  }

  if (typeof publicKeySeedOrVerifyFn === 'function') {
    if (!publicKeySeedOrVerifyFn(envelope.data, envelope.sig)) {
      return { ok: false, reason: 'sig_verify' };
    }
  } else {
    const nacl = require('tweetnacl');
    const seed = Buffer.from(publicKeySeedOrVerifyFn, 'hex');
    if (seed.length !== 32) {
      return { ok: false, reason: 'pubkey' };
    }
    const keyPair = nacl.sign.keyPair.fromSeed(seed);
    const ok = nacl.sign.detached.verify(
      Buffer.from(envelope.data, 'utf8'),
      Buffer.from(envelope.sig, 'hex'),
      keyPair.publicKey
    );
    if (!ok) {
      return { ok: false, reason: 'sig_verify' };
    }
  }

  return { ok: true, payload };
}

module.exports = {
  CONTENT_SUBSCRIPTION_TTL_SEC,
  CONTENT_SUBSCRIPTION_SKEW_SEC,
  CONTENT_SUBSCRIPTION_SCOPE,
  CONTENT_SUBSCRIPTION_VERSION,
  buildContentSubscriptionEnvelope,
  verifyContentSubscriptionEnvelope,
};
