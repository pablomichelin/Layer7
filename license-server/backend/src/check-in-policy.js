const DEFAULT_CHECK_IN_INTERVAL_HOURS = 168;
const DEFAULT_MAX_OFFLINE_HOURS = 336;

function parsePositiveIntEnv(name, fallback) {
  const raw = process.env[name];
  if (raw === undefined || raw === '') {
    return fallback;
  }

  const parsed = Number.parseInt(raw, 10);
  if (!Number.isFinite(parsed) || parsed <= 0) {
    return fallback;
  }

  return parsed;
}

function getCheckInPolicy() {
  return {
    checkInIntervalHours: parsePositiveIntEnv(
      'CHECK_IN_INTERVAL_HOURS',
      DEFAULT_CHECK_IN_INTERVAL_HOURS
    ),
    maxOfflineHours: parsePositiveIntEnv(
      'MAX_OFFLINE_HOURS',
      DEFAULT_MAX_OFFLINE_HOURS
    ),
  };
}

function formatExpiryDate(expiry) {
  if (!expiry) {
    return null;
  }

  if (expiry instanceof Date) {
    return expiry.toISOString().slice(0, 10);
  }

  return String(expiry).slice(0, 10);
}

const { normalizeFeatures } = require('./crud-validation');
const { buildContentSubscriptionEnvelope } = require('./content-subscription');
const { signData } = require('./crypto');
const { readSecret } = require('./secret-config');

/** Contrato 30.12 D4 — versão do envelope de check-in. */
const CHECK_IN_ENVELOPE_VERSION = 1;

function defaultCheckInSign(dataString) {
  const privateKeyHex = readSecret('ED25519_PRIVATE_KEY', {
    required: true,
    emptyMessage: 'ED25519_PRIVATE_KEY nao configurada no ambiente',
  });
  return signData(dataString, privateKeyHex);
}

function resolveCheckInIat(nowSec) {
  return Number.isFinite(nowSec) ? Math.trunc(nowSec) : Math.floor(Date.now() / 1000);
}

/**
 * Envelope v2 `{ data, sig }` — mesma chave Ed25519 das .lic (D5).
 * signFn injectável em testes (sem segredos no git).
 */
function wrapSignedCheckInEnvelope(payloadObj, options = {}) {
  if (!payloadObj || typeof payloadObj !== 'object') {
    throw new Error('payload check-in v2 invalido');
  }
  const signFn = typeof options.signFn === 'function' ? options.signFn : defaultCheckInSign;
  const data = JSON.stringify(payloadObj);
  const sig = signFn(data);
  if (typeof sig !== 'string' || !/^[0-9a-fA-F]{128}$/.test(sig)) {
    throw new Error('assinatura check-in invalida');
  }
  return { data, sig };
}

function buildActiveCheckInResponse(license, policy = getCheckInPolicy(), options = {}) {
  let features = 'base';
  try {
    features = normalizeFeatures(license.features || 'base');
  } catch (_err) {
    features = 'base';
  }

  const response = {
    status: 'active',
    expiry: formatExpiryDate(license.expiry),
    customer: license.customer_name || 'Unknown',
    features,
    check_in_interval_hours: policy.checkInIntervalHours,
    max_offline_hours: policy.maxOfflineHours,
  };

  /*
   * 30.9 / contrato 30.8: token de conteúdo só em check-in activo.
   * hardwareId vem do pedido (já validado pelo binding). Denied paths
   * usam buildDeniedCheckInResponse e nunca passam aqui.
   */
  const hardwareId = options.hardwareId;
  if (hardwareId) {
    response.content_subscription = buildContentSubscriptionEnvelope({
      hardwareId,
      licenseId: license.id,
      customerId: license.customer_id != null ? license.customer_id : null,
      features,
      nowSec: options.nowSec,
      ttlSec: options.ttlSec,
      signFn: options.signFn,
      jti: options.jti,
    });
  }

  return response;
}

/**
 * Payload interior v2 activo (antes do wrap) — contrato 30.12 §4.3.
 */
function buildActiveCheckInPayloadV2(license, policy = getCheckInPolicy(), options = {}) {
  const hardwareId = String(options.hardwareId || '').trim();
  const nonce = String(options.nonce || '').trim();
  if (!hardwareId || !nonce) {
    throw new Error('hardware_id e nonce obrigatorios para check-in v2');
  }

  const legacy = buildActiveCheckInResponse(license, policy, options);
  const payload = {
    v: CHECK_IN_ENVELOPE_VERSION,
    status: 'active',
    hardware_id: hardwareId,
    nonce,
    expiry: legacy.expiry,
    customer: legacy.customer,
    features: legacy.features,
    check_in_interval_hours: legacy.check_in_interval_hours,
    max_offline_hours: legacy.max_offline_hours,
    iat: resolveCheckInIat(options.nowSec),
  };
  if (legacy.content_subscription) {
    payload.content_subscription = legacy.content_subscription;
  }
  return payload;
}

function buildDeniedCheckInResponse(effectiveStatus, errorMessage) {
  return {
    status: effectiveStatus,
    error: errorMessage,
  };
}

/**
 * Payload interior v2 denied (antes do wrap) — contrato 30.12 §4.4.
 */
function buildDeniedCheckInPayloadV2(effectiveStatus, errorMessage, options = {}) {
  const hardwareId = String(options.hardwareId || '').trim();
  const nonce = String(options.nonce || '').trim();
  if (!hardwareId || !nonce) {
    throw new Error('hardware_id e nonce obrigatorios para check-in v2 denied');
  }
  return {
    v: CHECK_IN_ENVELOPE_VERSION,
    status: effectiveStatus,
    hardware_id: hardwareId,
    nonce,
    error: errorMessage,
    iat: resolveCheckInIat(options.nowSec),
  };
}

function mapActivationErrorToDeniedResponse(error) {
  if (error.message === 'Licenca revogada.') {
    return buildDeniedCheckInResponse('revoked', error.message);
  }

  if (error.message === 'Licenca expirada.') {
    return buildDeniedCheckInResponse('expired', error.message);
  }

  return null;
}

module.exports = {
  CHECK_IN_ENVELOPE_VERSION,
  DEFAULT_CHECK_IN_INTERVAL_HOURS,
  DEFAULT_MAX_OFFLINE_HOURS,
  buildActiveCheckInPayloadV2,
  buildActiveCheckInResponse,
  buildDeniedCheckInPayloadV2,
  buildDeniedCheckInResponse,
  formatExpiryDate,
  getCheckInPolicy,
  mapActivationErrorToDeniedResponse,
  wrapSignedCheckInEnvelope,
};
