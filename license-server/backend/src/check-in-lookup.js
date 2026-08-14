const { createHttpError } = require('./http-error');

/**
 * Licença visível no fluxo normal de check-in (BG-077 / 30.13).
 * Cliente e licença não arquivados.
 */
const VISIBLE_CHECK_IN_LICENSE_SQL = `
      SELECT l.*, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.license_key = $1
         AND l.archived_at IS NULL
         AND (c.id IS NULL OR c.archived_at IS NULL)
`;

/**
 * P1-1 / BG-128: chave antiga arquivada por replace/DELETE, mas ainda
 * revoked ou expired (persistido ou por data). O cliente C só invalida
 * envelope assinado com esses status — 404/`fail` deixava o `.lic` vivo.
 */
const ARCHIVED_DENIED_CHECK_IN_LICENSE_SQL = `
      SELECT l.*, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.license_key = $1
         AND l.archived_at IS NOT NULL
         AND (
           l.status IN ('revoked', 'expired')
           OR (l.status = 'active' AND l.expiry < CURRENT_DATE)
         )
       ORDER BY l.archived_at DESC
       LIMIT 1
`;

async function loadLicenseForCheckIn(queryable, licenseKey) {
  const visible = await queryable.query(VISIBLE_CHECK_IN_LICENSE_SQL, [licenseKey]);
  if (visible.rows.length > 0) {
    return visible.rows[0];
  }

  const archivedDenied = await queryable.query(
    ARCHIVED_DENIED_CHECK_IN_LICENSE_SQL,
    [licenseKey]
  );
  if (archivedDenied.rows.length > 0) {
    return archivedDenied.rows[0];
  }

  throw createHttpError(404, 'Licenca nao encontrada.');
}

module.exports = {
  ARCHIVED_DENIED_CHECK_IN_LICENSE_SQL,
  VISIBLE_CHECK_IN_LICENSE_SQL,
  loadLicenseForCheckIn,
};
