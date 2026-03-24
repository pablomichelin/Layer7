require('dotenv').config();
const bcrypt = require('bcryptjs');
const pool = require('./src/db');

const ADMIN_EMAIL = 'pablo@systemup.inf.br';
const ADMIN_NAME = 'Pablo Michelin';
const ADMIN_PASSWORD = 'P@blo.147';
const BCRYPT_ROUNDS = 12;

async function seed() {
  try {
    const existing = await pool.query(
      'SELECT id FROM admins WHERE email = $1',
      [ADMIN_EMAIL]
    );

    if (existing.rows.length > 0) {
      console.log(`[SEED] Admin "${ADMIN_EMAIL}" ja existe (id=${existing.rows[0].id}). Nada a fazer.`);
      process.exit(0);
    }

    const hash = await bcrypt.hash(ADMIN_PASSWORD, BCRYPT_ROUNDS);
    const result = await pool.query(
      'INSERT INTO admins (email, name, password_hash) VALUES ($1, $2, $3) RETURNING id',
      [ADMIN_EMAIL, ADMIN_NAME, hash]
    );

    console.log(`[SEED] Admin criado: ${ADMIN_EMAIL} (id=${result.rows[0].id})`);
    process.exit(0);
  } catch (err) {
    console.error('[SEED] Erro:', err.message);
    process.exit(1);
  }
}

seed();
