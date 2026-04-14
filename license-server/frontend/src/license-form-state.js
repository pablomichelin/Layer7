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
    features: license?.features || 'full',
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
