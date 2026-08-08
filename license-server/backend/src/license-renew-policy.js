const { createHttpError } = require('./http-error');
const { normalizeExpiryDate } = require('./crud-validation');
const { getEffectiveLicenseState } = require('./license-state');

const ALLOWED_RENEW_DAYS = new Set([30, 90, 365]);

function parseRenewPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    throw createHttpError(400, 'Payload invalido.');
  }

  const unexpected = Object.keys(body).filter((key) => key !== 'days');
  if (unexpected.length > 0) {
    throw createHttpError(400, `Payload contem campos nao permitidos: ${unexpected.join(', ')}`);
  }

  if (!Object.prototype.hasOwnProperty.call(body, 'days')) {
    throw createHttpError(400, 'days obrigatorio.');
  }

  const days = Number.parseInt(body.days, 10);
  if (!Number.isInteger(days) || !ALLOWED_RENEW_DAYS.has(days)) {
    throw createHttpError(400, 'days deve ser 30, 90 ou 365.');
  }

  return { days };
}

function todayUtcDateString(now = new Date()) {
  return now.toISOString().slice(0, 10);
}

function addDaysToIsoDate(isoDate, days) {
  const date = new Date(`${isoDate}T00:00:00.000Z`);
  date.setUTCDate(date.getUTCDate() + days);
  return date.toISOString().slice(0, 10);
}

/**
 * Renova a partir do max(hoje, expiry actual): licença futura alonga;
 * expirada/passada parte de hoje.
 */
function computeRenewedExpiry(currentExpiry, days, now = new Date()) {
  const today = todayUtcDateString(now);
  const current = normalizeExpiryDate(currentExpiry) || today;
  const base = current >= today ? current : today;
  return addDaysToIsoDate(base, days);
}

function createLicenseRenewGuardError(license) {
  const state = getEffectiveLicenseState(license);
  if (state.revoked) {
    return createHttpError(409, 'Licenca revogada nao pode ser renovada. Use substituicao (P1d) ou desrevogacao governada.');
  }
  return null;
}

module.exports = {
  ALLOWED_RENEW_DAYS,
  computeRenewedExpiry,
  createLicenseRenewGuardError,
  parseRenewPayload,
};
