const pool = require('./db');

async function ensureCheckInSchema() {
  await pool.query(`
    CREATE TABLE IF NOT EXISTS check_ins_log (
      id          SERIAL PRIMARY KEY,
      license_id  INTEGER REFERENCES licenses(id) ON DELETE CASCADE,
      hardware_id VARCHAR(64),
      ip_address  VARCHAR(45),
      user_agent  VARCHAR(255),
      result      VARCHAR(20) CHECK (result IN ('active', 'revoked', 'expired', 'fail')),
      error_message TEXT,
      created_at  TIMESTAMP DEFAULT NOW()
    )
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_check_ins_log_license_id
      ON check_ins_log(license_id)
  `);

  await pool.query(`
    CREATE INDEX IF NOT EXISTS idx_check_ins_log_created_at
      ON check_ins_log(created_at)
  `);
}

module.exports = {
  ensureCheckInSchema,
};
