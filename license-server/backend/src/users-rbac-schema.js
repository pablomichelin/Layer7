const pool = require('./db');
const { OWNER_PERMISSIONS } = require('./admin-permissions');

const ENSURE_USERS_RBAC_COLUMNS_SQL = `
    ALTER TABLE admins
      ADD COLUMN IF NOT EXISTS is_owner BOOLEAN NOT NULL DEFAULT FALSE,
      ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE,
      ADD COLUMN IF NOT EXISTS permissions JSONB NOT NULL DEFAULT '[]'::jsonb
`;

const PROMOTE_LEGACY_OWNER_SQL = `
    UPDATE admins
        SET is_owner = TRUE,
            permissions = $1::jsonb
      WHERE id = (
        SELECT id
          FROM admins
         WHERE NOT EXISTS (
           SELECT 1 FROM admins WHERE is_owner = TRUE
         )
         ORDER BY id ASC
         LIMIT 1
      )
  RETURNING id
`;

const REPAIR_OWNER_PERMISSIONS_SQL = `
    UPDATE admins
        SET permissions = $1::jsonb
      WHERE is_owner = TRUE
        AND (
          permissions IS NULL
          OR jsonb_typeof(permissions) <> 'array'
          OR NOT (permissions ? '*')
        )
`;

const COUNT_OWNERS_SQL = `
    SELECT COUNT(*)::integer AS owner_count
      FROM admins
     WHERE is_owner = TRUE
`;

const MULTIPLE_OWNERS_WARNING =
  '[RBAC] Multiplos owners detectados; nenhum promovido nem demovido.';

function warnMultipleOwners(ownerCount, warn = console.warn) {
  warn(`${MULTIPLE_OWNERS_WARNING} owner_count=${ownerCount}`);
}

async function ensureUsersRbacSchema(queryable = pool, { warn = console.warn } = {}) {
  await queryable.query(ENSURE_USERS_RBAC_COLUMNS_SQL);

  const promoteResult = await queryable.query(
    PROMOTE_LEGACY_OWNER_SQL,
    [JSON.stringify(OWNER_PERMISSIONS)]
  );
  const promotedId = promoteResult.rows[0]?.id ?? null;

  await queryable.query(
    REPAIR_OWNER_PERMISSIONS_SQL,
    [JSON.stringify(OWNER_PERMISSIONS)]
  );

  const ownerCountResult = await queryable.query(COUNT_OWNERS_SQL);
  const ownerCount = ownerCountResult.rows[0].owner_count;

  if (ownerCount > 1) {
    warnMultipleOwners(ownerCount, warn);
  }

  return { ownerCount, promotedId };
}

module.exports = {
  COUNT_OWNERS_SQL,
  ENSURE_USERS_RBAC_COLUMNS_SQL,
  MULTIPLE_OWNERS_WARNING,
  PROMOTE_LEGACY_OWNER_SQL,
  REPAIR_OWNER_PERMISSIONS_SQL,
  ensureUsersRbacSchema,
  warnMultipleOwners,
};
