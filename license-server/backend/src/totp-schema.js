const pool = require('./db');

async function ensureTotpSchema() {
  await pool.query(`
    ALTER TABLE admins
      ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64),
      ADD COLUMN IF NOT EXISTS totp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
      ADD COLUMN IF NOT EXISTS totp_pending_secret VARCHAR(64)
  `);
}

module.exports = {
  ensureTotpSchema,
};
