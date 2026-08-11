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

function buildDeniedCheckInResponse(effectiveStatus, errorMessage) {
  return {
    status: effectiveStatus,
    error: errorMessage,
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
  DEFAULT_CHECK_IN_INTERVAL_HOURS,
  DEFAULT_MAX_OFFLINE_HOURS,
  buildActiveCheckInResponse,
  buildDeniedCheckInResponse,
  formatExpiryDate,
  getCheckInPolicy,
  mapActivationErrorToDeniedResponse,
};
