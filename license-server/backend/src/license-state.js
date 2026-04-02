const { isLicenseExpired, normalizeStoredHardwareId } = require('./crud-validation');

const LICENSE_SQL_ACTIVE_CONDITION = `status = 'active' AND expiry >= CURRENT_DATE`;
const LICENSE_SQL_EXPIRED_CONDITION = `(status = 'expired' OR (status = 'active' AND expiry < CURRENT_DATE))`;
const LICENSE_SQL_REVOKED_CONDITION = `status = 'revoked'`;

function getEffectiveLicenseState(license) {
  const persistedStatus = typeof license?.status === 'string' ? license.status : null;
  const expiredByDate = isLicenseExpired(license);
  const revoked = persistedStatus === 'revoked';
  const expiredPersisted = persistedStatus === 'expired';
  const effectiveStatus = revoked
    ? 'revoked'
    : (expiredPersisted || expiredByDate ? 'expired' : 'active');
  const normalizedHardwareId = normalizeStoredHardwareId(license?.hardware_id) || license?.hardware_id || null;

  return {
    persistedStatus,
    effectiveStatus,
    expiredByDate,
    expiredPersisted,
    revoked,
    active: effectiveStatus === 'active',
    activated: Boolean(normalizedHardwareId),
    normalizedHardwareId,
  };
}

function applyEffectiveLicenseState(license) {
  if (!license || typeof license !== 'object') {
    return license;
  }

  return {
    ...license,
    status: getEffectiveLicenseState(license).effectiveStatus,
  };
}

module.exports = {
  applyEffectiveLicenseState,
  getEffectiveLicenseState,
  LICENSE_SQL_ACTIVE_CONDITION,
  LICENSE_SQL_EXPIRED_CONDITION,
  LICENSE_SQL_REVOKED_CONDITION,
};
