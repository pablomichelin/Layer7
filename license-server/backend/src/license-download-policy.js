const { createHttpError } = require('./http-error');
const { normalizeStoredHardwareId } = require('./crud-validation');
const { getEffectiveLicenseState } = require('./license-state');

function getDownloadHardwareId(license) {
  return normalizeStoredHardwareId(license.hardware_id) || license.hardware_id || null;
}

function createLicenseDownloadGuardError(license) {
  const effectiveHardwareId = getDownloadHardwareId(license);
  const effectiveStatus = getEffectiveLicenseState(license).effectiveStatus;

  if (!effectiveHardwareId) {
    return {
      error: createHttpError(409, 'Licenca ainda nao foi activada.'),
      reason: 'license_not_activated',
      metadata: { license_id: license.id },
    };
  }

  if (effectiveStatus !== 'active') {
    return {
      error: createHttpError(409, 'Licenca nao esta em estado valido para download.'),
      reason: 'license_state_invalid_for_download',
      metadata: { license_id: license.id, status: effectiveStatus },
    };
  }

  return null;
}

module.exports = {
  createLicenseDownloadGuardError,
  getDownloadHardwareId,
};
