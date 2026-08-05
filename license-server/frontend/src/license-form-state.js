/*
 * SKU presets for license features (ADR-0025 / passo 20.4).
 * Valores canónicos emitidos pelo license-server após normalizeFeatures.
 */
export const LICENSE_FEATURE_PRESETS = [
  { value: 'base', label: 'Standard (base)' },
  { value: 'base,identity', label: 'Identity Add-on' },
  { value: 'base,mitm', label: 'MITM Add-on' },
  { value: 'base,identity,mitm', label: 'Identity + MITM' },
];

export const LICENSE_FEATURES_DEFAULT = 'base';

export function toLicenseDateInputValue(value) {
  if (typeof value !== 'string' || !value) {
    return '';
  }

  return value.slice(0, 10);
}

export function buildLicenseFormState(license) {
  return {
    customer_id: String(license?.customer_id ?? ''),
    expiry: toLicenseDateInputValue(license?.expiry),
    features: license?.features || LICENSE_FEATURES_DEFAULT,
    notes: license?.notes || '',
  };
}

export function buildLicenseSavePayload(form) {
  return {
    customer_id: Number.parseInt(form.customer_id, 10),
    expiry: form.expiry,
    features: form.features,
    notes: form.notes,
  };
}

export function isLicenseCustomerChangeBlocked({ isEdit, license }) {
  return Boolean(isEdit && license && (license.hardware_id || license.activated_at));
}

export function isKnownLicenseFeaturePreset(features) {
  return LICENSE_FEATURE_PRESETS.some((preset) => preset.value === features);
}
