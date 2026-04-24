const { createHttpError } = require('./http-error');
const { normalizeStoredHardwareId } = require('./crud-validation');

function getDownloadHardwareId(license) {
  return normalizeStoredHardwareId(license.hardware_id) || license.hardware_id || null;
}

function createLicenseDownloadGuardError(license) {
  const effectiveHardwareId = getDownloadHardwareId(license);

  if (!effectiveHardwareId) {
    return {
      error: createHttpError(409, 'Licenca ainda nao foi activada.'),
      reason: 'license_not_activated',
      metadata: { license_id: license.id },
    };
  }

  if (license.status !== 'active') {
    return {
      error: createHttpError(409, 'Licenca nao esta em estado valido para download.'),
      reason: 'license_state_invalid_for_download',
      metadata: { license_id: license.id, status: license.status },
    };
  }

  return null;
}

module.exports = {
  createLicenseDownloadGuardError,
  getDownloadHardwareId,
};
