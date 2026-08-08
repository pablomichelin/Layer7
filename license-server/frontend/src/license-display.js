const SKU_LABELS = {
  base: 'Standard (base)',
  'base,identity': 'Identity',
  'base,mitm': 'MITM',
  'base,identity,mitm': 'Identity + MITM',
  full: 'Legado (full→base)',
};

export function formatSkuLabel(features) {
  if (!features) {
    return '—';
  }

  return SKU_LABELS[features] || features;
}

export function isLicenseBound(license) {
  return Boolean(license?.hardware_id && String(license.hardware_id).trim());
}
