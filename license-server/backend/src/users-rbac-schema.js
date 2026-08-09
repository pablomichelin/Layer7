const pool = require('./db');
const { OWNER_PERMISSIONS } = require('./admin-permissions');

async function ensureUsersRbacSchema() {
  await pool.query(`
    ALTER TABLE admins
      ADD COLUMN IF NOT EXISTS is_owner BOOLEAN NOT NULL DEFAULT FALSE,
      ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE,
      ADD COLUMN IF NOT EXISTS permissions JSONB NOT NULL DEFAULT '[]'::jsonb
  `);

  // Contas existentes sem owner → promover a owner (bootstrap / legado).
  await pool.query(
    `UPDATE admins
        SET is_owner = TRUE,
            permissions = $1::jsonb
      WHERE NOT EXISTS (
        SELECT 1 FROM admins WHERE is_owner = TRUE
      )`,
    [JSON.stringify(OWNER_PERMISSIONS)]
  );

  await pool.query(
    `UPDATE admins
        SET permissions = $1::jsonb
      WHERE is_owner = TRUE
        AND (
          permissions IS NULL
          OR jsonb_typeof(permissions) <> 'array'
          OR NOT (permissions ? '*')
        )`,
    [JSON.stringify(OWNER_PERMISSIONS)]
  );
}

module.exports = {
  ensureUsersRbacSchema,
};
