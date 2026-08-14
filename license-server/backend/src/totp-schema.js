const pool = require('./db');

async function ensureTotpSchema(queryable = pool) {
  await queryable.query(`
    ALTER TABLE admins
      ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(64),
      ADD COLUMN IF NOT EXISTS totp_enabled BOOLEAN NOT NULL DEFAULT FALSE,
      ADD COLUMN IF NOT EXISTS totp_pending_secret VARCHAR(64)
  `);

  await queryable.query(`
    CREATE TABLE IF NOT EXISTS admin_totp_challenges (
      jti VARCHAR(64) PRIMARY KEY,
      admin_id INTEGER NOT NULL REFERENCES admins(id) ON DELETE CASCADE,
      expires_at TIMESTAMPTZ NOT NULL,
      used_at TIMESTAMPTZ,
      created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
    )
  `);

  await queryable.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_totp_challenges_admin
      ON admin_totp_challenges(admin_id)
  `);
  await queryable.query(`
    CREATE INDEX IF NOT EXISTS idx_admin_totp_challenges_expires
      ON admin_totp_challenges(expires_at)
  `);
}

module.exports = {
  ensureTotpSchema,
};
