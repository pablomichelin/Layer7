/**
 * Contadores da ficha cliente a partir das licenças já devolvidas pela API.
 * Status efectivo vem do backend (applyEffectiveLicenseState).
 */
export function summarizeCustomerLicenses(licenses, now = new Date()) {
  const rows = Array.isArray(licenses) ? licenses : [];
  const today = now.toISOString().slice(0, 10);
  const limit30 = new Date(now);
  limit30.setUTCDate(limit30.getUTCDate() + 30);
  const limit30Str = limit30.toISOString().slice(0, 10);

  let active = 0;
  let revoked = 0;
  let expired = 0;
  let expiring30d = 0;
  let bound = 0;

  for (const license of rows) {
    const status = license?.status || '';
    if (status === 'active') {
      active += 1;
      const expiry = typeof license.expiry === 'string'
        ? license.expiry.slice(0, 10)
        : '';
      if (expiry && expiry >= today && expiry <= limit30Str) {
        expiring30d += 1;
      }
    } else if (status === 'revoked') {
      revoked += 1;
    } else if (status === 'expired') {
      expired += 1;
    }

    if (license?.hardware_id && String(license.hardware_id).trim()) {
      bound += 1;
    }
  }

  return {
    total: rows.length,
    active,
    revoked,
    expired,
    expiring30d,
    bound,
  };
}
