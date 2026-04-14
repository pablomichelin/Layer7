const pool = require('./db');
const { createHttpError, isHttpError } = require('./http-error');

async function runInTransaction(work) {
  const client = await pool.connect();

  try {
    await client.query('BEGIN');
    const result = await work(client);
    await client.query('COMMIT');
    return result;
  } catch (error) {
    try {
      await client.query('ROLLBACK');
    } catch (rollbackError) {
      console.error('[CRUD] Rollback error:', rollbackError.message);
    }
    throw error;
  } finally {
    client.release();
  }
}

async function ensureCrudIntegritySchema() {
  await pool.query(`
    ALTER TABLE customers
      ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ,
      ADD COLUMN IF NOT EXISTS archived_by_admin_id INTEGER REFERENCES admins(id) ON DELETE SET NULL
  `);

  await pool.query(`
    ALTER TABLE licenses
      ADD COLUMN IF NOT EXISTS archived_at TIMESTAMPTZ,
      ADD COLUMN IF NOT EXISTS archived_by_admin_id INTEGER REFERENCES admins(id) ON DELETE SET NULL
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_customers_archived_at
      ON customers(archived_at)
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_licenses_archived_at
      ON licenses(archived_at)
  `);
}

module.exports = {
  createHttpError,
  ensureCrudIntegritySchema,
  isHttpError,
  runInTransaction,
};
