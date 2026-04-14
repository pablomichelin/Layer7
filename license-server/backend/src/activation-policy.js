const { createHttpError } = require('./http-error');
const { normalizeStoredHardwareId } = require('./crud-validation');

function createActivationStateError(license, effectiveState) {
  if (effectiveState.revoked) {
    const error = createHttpError(409, 'Licenca revogada.');
    error.licenseId = license.id;
    return error;
  }

  if (effectiveState.effectiveStatus === 'expired') {
    const error = createHttpError(409, 'Licenca expirada.');
    error.licenseId = license.id;
    return error;
  }

  return null;
}

function getBoundHardwareId(license) {
  return normalizeStoredHardwareId(license.hardware_id) || license.hardware_id;
}

function createHardwareBindingError(license, requestedHardwareId) {
  const boundHardwareId = getBoundHardwareId(license);

  if (!boundHardwareId || boundHardwareId === requestedHardwareId) {
    return null;
  }

  const error = createHttpError(409, 'Hardware ID nao corresponde.');
  error.licenseId = license.id;
  return error;
}

module.exports = {
  createActivationStateError,
  createHardwareBindingError,
  getBoundHardwareId,
};
