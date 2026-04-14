const { createHttpError } = require('./http-error');
const { getEffectiveLicenseState } = require('./license-state');

function listChangedLicenseFields(existingLicense, payload) {
  const changedFields = [];

  if (Object.prototype.hasOwnProperty.call(payload, 'customerId')
    && payload.customerId !== existingLicense.customer_id) {
    changedFields.push('customer_id');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'expiry')
    && payload.expiry !== existingLicense.expiry) {
    changedFields.push('expiry');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'features')
    && payload.features !== (existingLicense.features || 'full')) {
    changedFields.push('features');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'notes')
    && payload.notes !== existingLicense.notes) {
    changedFields.push('notes');
  }

  return changedFields;
}

function createLicenseUpdateGuardError(existingLicense, changedFields) {
  const existingState = getEffectiveLicenseState(existingLicense);

  if (changedFields.includes('customer_id')
    && (existingState.activated || Boolean(existingLicense.activated_at))) {
    return createHttpError(
      409,
      'Licenca activada/bindada nao permite mudar customer_id. Crie nova licenca para outro cliente.'
    );
  }

  return null;
}

module.exports = {
  createLicenseUpdateGuardError,
  listChangedLicenseFields,
};
