const { createHttpError } = require('./http-error');
const { normalizeExpiryDate } = require('./crud-validation');
const { getEffectiveLicenseState } = require('./license-state');

const REPLACE_REASON_MIN = 10;
const REPLACE_REASON_MAX = 500;

function parseReplacePayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    throw createHttpError(400, 'Payload invalido.');
  }

  const allowed = ['reason', 'expiry'];
  const unexpected = Object.keys(body).filter((key) => !allowed.includes(key));
  if (unexpected.length > 0) {
    throw createHttpError(400, `Payload contem campos nao permitidos: ${unexpected.join(', ')}`);
  }

  if (typeof body.reason !== 'string') {
    throw createHttpError(400, 'reason obrigatorio.');
  }

  const reason = body.reason.trim();
  if (reason.length < REPLACE_REASON_MIN) {
    throw createHttpError(400, `reason deve ter pelo menos ${REPLACE_REASON_MIN} caracteres.`);
  }
  if (reason.length > REPLACE_REASON_MAX) {
    throw createHttpError(400, `reason excede ${REPLACE_REASON_MAX} caracteres.`);
  }

  let expiry;
  if (body.expiry !== undefined) {
    if (typeof body.expiry !== 'string') {
      throw createHttpError(400, 'expiry invalido.');
    }
    expiry = normalizeExpiryDate(body.expiry);
    if (!expiry) {
      throw createHttpError(400, 'expiry invalido (YYYY-MM-DD).');
    }
  }

  return { reason, expiry };
}

function createLicenseReplaceGuardError(license) {
  const state = getEffectiveLicenseState(license);

  if (!state.revoked) {
    return createHttpError(
      409,
      'So licencas revogadas podem ser substituidas. Revogue primeiro ou use renovacao/rebind.'
    );
  }

  return null;
}

function buildReplacementNotes(previousLicense, reason) {
  const header = `Substitui #${previousLicense.id}: ${reason}`;
  const previousNotes = typeof previousLicense.notes === 'string' ? previousLicense.notes.trim() : '';
  if (!previousNotes) {
    return header;
  }
  return `${header}\n---\n${previousNotes}`.slice(0, 2000);
}

function resolveReplacementExpiry(previousLicense, requestedExpiry) {
  if (requestedExpiry) {
    return requestedExpiry;
  }
  return normalizeExpiryDate(previousLicense.expiry) || previousLicense.expiry;
}

module.exports = {
  REPLACE_REASON_MIN,
  buildReplacementNotes,
  createLicenseReplaceGuardError,
  parseReplacePayload,
  resolveReplacementExpiry,
};
