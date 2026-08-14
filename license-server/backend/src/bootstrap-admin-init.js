const { OWNER_PERMISSIONS } = require('./admin-permissions');

const BOOTSTRAP_LOCK_SQL = 'LOCK TABLE admins IN SHARE ROW EXCLUSIVE MODE';
const BOOTSTRAP_COUNT_SQL = 'SELECT COUNT(*)::integer AS total FROM admins';
const BOOTSTRAP_INSERT_SQL = `
INSERT INTO admins (email, name, password_hash, is_owner, is_active, permissions)
VALUES ($1, $2, $3, TRUE, TRUE, $4::jsonb)
RETURNING id, email, name, is_owner, is_active, created_at
`.trim();

const BOOTSTRAP_ALREADY_EXISTS_ERROR =
  'Bootstrap inicial recusado: ja existe pelo menos um admin';

async function insertInitialOwnerAdmin(client, { email, name, passwordHash }) {
  await client.query(BOOTSTRAP_LOCK_SQL);

  const adminCountResult = await client.query(BOOTSTRAP_COUNT_SQL);
  const totalAdmins = adminCountResult.rows[0].total;

  if (totalAdmins > 0) {
    throw new Error(BOOTSTRAP_ALREADY_EXISTS_ERROR);
  }

  const insertResult = await client.query(BOOTSTRAP_INSERT_SQL, [
    email,
    name,
    passwordHash,
    JSON.stringify(OWNER_PERMISSIONS),
  ]);

  return insertResult.rows[0];
}

async function runBootstrapInitTransaction(client, identity, { audit } = {}) {
  try {
    await client.query('BEGIN');
    const admin = await insertInitialOwnerAdmin(client, identity);
    if (typeof audit === 'function') {
      await audit(client, admin);
    }
    await client.query('COMMIT');
    return admin;
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  }
}

module.exports = {
  BOOTSTRAP_ALREADY_EXISTS_ERROR,
  BOOTSTRAP_COUNT_SQL,
  BOOTSTRAP_INSERT_SQL,
  BOOTSTRAP_LOCK_SQL,
  insertInitialOwnerAdmin,
  runBootstrapInitTransaction,
};
