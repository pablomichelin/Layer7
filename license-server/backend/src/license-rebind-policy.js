const { createHttpError } = require('./http-error');
const { normalizeStoredHardwareId } = require('./crud-validation');
const { getEffectiveLicenseState } = require('./license-state');

const REBIND_MODES = new Set(['unbind', 'set']);
const REBIND_REASON_MIN = 10;
const REBIND_REASON_MAX = 500;

function parseRebindPayload(body) {
  if (!body || typeof body !== 'object' || Array.isArray(body)) {
    throw createHttpError(400, 'Payload invalido.');
  }

  const allowed = ['reason', 'mode', 'new_hardware_id'];
  const unexpected = Object.keys(body).filter((key) => !allowed.includes(key));
  if (unexpected.length > 0) {
    throw createHttpError(400, `Payload contem campos nao permitidos: ${unexpected.join(', ')}`);
  }

  if (typeof body.reason !== 'string') {
    throw createHttpError(400, 'reason obrigatorio.');
  }

  const reason = body.reason.trim();
  if (reason.length < REBIND_REASON_MIN) {
    throw createHttpError(400, `reason deve ter pelo menos ${REBIND_REASON_MIN} caracteres.`);
  }
  if (reason.length > REBIND_REASON_MAX) {
    throw createHttpError(400, `reason excede ${REBIND_REASON_MAX} caracteres.`);
  }

  let mode = 'unbind';
  if (body.mode !== undefined) {
    if (typeof body.mode !== 'string') {
      throw createHttpError(400, 'mode invalido.');
    }
    mode = body.mode.trim().toLowerCase();
    if (!REBIND_MODES.has(mode)) {
      throw createHttpError(400, 'mode deve ser unbind ou set.');
    }
  }

  let newHardwareId;
  if (mode === 'set') {
    if (body.new_hardware_id === undefined) {
      throw createHttpError(400, 'new_hardware_id obrigatorio no mode set.');
    }
    if (typeof body.new_hardware_id !== 'string') {
      throw createHttpError(400, 'new_hardware_id invalido.');
    }
    newHardwareId = normalizeStoredHardwareId(body.new_hardware_id);
    if (!newHardwareId) {
      throw createHttpError(400, 'new_hardware_id invalido.');
    }
  } else if (body.new_hardware_id !== undefined) {
    throw createHttpError(400, 'new_hardware_id so e permitido no mode set.');
  }

  return { reason, mode, newHardwareId };
}

function createLicenseRebindGuardError(license, { mode, newHardwareId }) {
  const state = getEffectiveLicenseState(license);

  if (state.revoked) {
    return createHttpError(409, 'Licenca revogada nao pode ser rebindada. Use substituicao (P1d).');
  }

  const previousHardwareId = normalizeStoredHardwareId(license.hardware_id) || license.hardware_id || null;

  if (mode === 'unbind') {
    if (!previousHardwareId) {
      return createHttpError(409, 'Licenca ja esta unbound.');
    }
    return null;
  }

  if (!previousHardwareId) {
    return createHttpError(409, 'Licenca unbound: use activacao online em vez de set.');
  }

  if (previousHardwareId === newHardwareId) {
    return createHttpError(409, 'new_hardware_id e igual ao bind actual.');
  }

  return null;
}

module.exports = {
  REBIND_MODES,
  REBIND_REASON_MIN,
  createLicenseRebindGuardError,
  parseRebindPayload,
};
