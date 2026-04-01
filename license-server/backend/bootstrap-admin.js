#!/usr/bin/env node

require('dotenv').config();
const bcrypt = require('bcryptjs');
const pool = require('./src/db');
const {
  auditAdminEvent,
  ensureAdminSurfaceSchema,
  normalizeAdminEmail,
} = require('./src/admin-surface');
const { readSecret } = require('./src/secret-config');

const BCRYPT_ROUNDS = 12;
const VALID_COMMANDS = new Set(['init', 'reset-password', 'status']);

function printUsage(exitCode = 0) {
  const usage = [
    'Uso:',
    '  node bootstrap-admin.js init',
    '  node bootstrap-admin.js reset-password --email admin@example.com',
    '  node bootstrap-admin.js status',
    '',
    'Variaveis suportadas:',
    '  ADMIN_BOOTSTRAP_EMAIL',
    '  ADMIN_BOOTSTRAP_NAME',
    '  ADMIN_BOOTSTRAP_PASSWORD ou ADMIN_BOOTSTRAP_PASSWORD_FILE',
    '',
    'Compatibilidade legada do seed:',
    '  ADMIN_EMAIL, ADMIN_NAME e ADMIN_PASSWORD continuam aceites via seed.js',
  ].join('\n');

  const stream = exitCode === 0 ? process.stdout : process.stderr;
  stream.write(`${usage}\n`);
  process.exit(exitCode);
}

function parseArgs(argv) {
  const [command, ...rest] = argv;

  if (!command || command === '--help' || command === '-h') {
    printUsage(0);
  }

  if (!VALID_COMMANDS.has(command)) {
    console.error(`[BOOTSTRAP] Comando invalido: ${command}`);
    printUsage(1);
  }

  const flags = {};

  for (let index = 0; index < rest.length; index += 1) {
    const token = rest[index];

    if (token === '--email') {
      flags.email = rest[index + 1];
      index += 1;
      continue;
    }

    console.error(`[BOOTSTRAP] Argumento invalido: ${token}`);
    printUsage(1);
  }

  return { command, flags };
}

function readBootstrapPassword({ allowLegacyEnv = false } = {}) {
  const password = readSecret('ADMIN_BOOTSTRAP_PASSWORD');

  if (password) {
    return password;
  }

  if (allowLegacyEnv) {
    return process.env.ADMIN_PASSWORD?.trim() || '';
  }

  return '';
}

function getBootstrapIdentity({ allowLegacyEnv = false, emailOverride = '' } = {}) {
  const emailCandidate = emailOverride
    || process.env.ADMIN_BOOTSTRAP_EMAIL
    || (allowLegacyEnv ? process.env.ADMIN_EMAIL : '');
  const nameCandidate = process.env.ADMIN_BOOTSTRAP_NAME
    || (allowLegacyEnv ? process.env.ADMIN_NAME : '');
  const email = normalizeAdminEmail(emailCandidate);
  const name = typeof nameCandidate === 'string' ? nameCandidate.trim() : '';
  const password = readBootstrapPassword({ allowLegacyEnv });

  return { email, name, password };
}

function validatePassword(password) {
  if (!password) {
    throw new Error('ADMIN_BOOTSTRAP_PASSWORD obrigatoria');
  }

  if (password.length < 12) {
    throw new Error('ADMIN_BOOTSTRAP_PASSWORD deve ter pelo menos 12 caracteres');
  }

  if (password.startsWith('CHANGE_ME')) {
    throw new Error('ADMIN_BOOTSTRAP_PASSWORD nao pode usar placeholder CHANGE_ME');
  }
}

async function logBootstrapEvent(client, payload) {
  await auditAdminEvent({
    client,
    strict: true,
    component: 'bootstrap',
    req: null,
    ...payload,
  });
}

async function runStatus() {
  const result = await pool.query(
    `SELECT
        COUNT(*)::integer AS total_admins,
        MIN(created_at) AS first_admin_created_at,
        MAX(created_at) AS last_admin_created_at
       FROM admins`
  );
  const row = result.rows[0];

  console.log(JSON.stringify({
    total_admins: row.total_admins,
    first_admin_created_at: row.first_admin_created_at,
    last_admin_created_at: row.last_admin_created_at,
  }, null, 2));
}

async function runInit() {
  const identity = getBootstrapIdentity({ allowLegacyEnv: true });

  if (!identity.email) {
    throw new Error('ADMIN_BOOTSTRAP_EMAIL obrigatorio para init');
  }

  if (!identity.name) {
    throw new Error('ADMIN_BOOTSTRAP_NAME obrigatorio para init');
  }

  validatePassword(identity.password);

  const client = await pool.connect();

  try {
    await client.query('BEGIN');

    const adminCountResult = await client.query('SELECT COUNT(*)::integer AS total FROM admins');
    const totalAdmins = adminCountResult.rows[0].total;

    if (totalAdmins > 0) {
      throw new Error('Bootstrap inicial recusado: ja existe pelo menos um admin');
    }

    const passwordHash = await bcrypt.hash(identity.password, BCRYPT_ROUNDS);
    const insertResult = await client.query(
      `INSERT INTO admins (email, name, password_hash)
       VALUES ($1, $2, $3)
       RETURNING id, email, name, created_at`,
      [identity.email, identity.name, passwordHash]
    );
    const admin = insertResult.rows[0];

    await logBootstrapEvent(client, {
      adminId: admin.id,
      actorIdentifier: admin.email,
      eventType: 'initial_admin_created',
      result: 'success',
      reason: 'bootstrap_init',
      metadata: {
        mode: 'init',
        admin_name: admin.name,
      },
    });

    await client.query('COMMIT');

    const createdAtIso = new Date(admin.created_at).toISOString();

    console.log(
      `[BOOTSTRAP] Admin inicial criado: ${admin.email} (id=${admin.id}, created_at=${createdAtIso})`
    );
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
}

async function runResetPassword(emailOverride) {
  const identity = getBootstrapIdentity({ emailOverride });

  if (!identity.email) {
    throw new Error('Email alvo obrigatorio para reset-password');
  }

  validatePassword(identity.password);

  const client = await pool.connect();

  try {
    await client.query('BEGIN');

    const adminResult = await client.query(
      'SELECT id, email, name FROM admins WHERE LOWER(email) = $1',
      [identity.email]
    );

    if (adminResult.rows.length === 0) {
      throw new Error(`Admin nao encontrado: ${identity.email}`);
    }

    const admin = adminResult.rows[0];
    const passwordHash = await bcrypt.hash(identity.password, BCRYPT_ROUNDS);

    await client.query(
      'UPDATE admins SET password_hash = $1 WHERE id = $2',
      [passwordHash, admin.id]
    );
    await client.query(
      `UPDATE admin_sessions
          SET revoked_at = NOW()
        WHERE admin_id = $1
          AND revoked_at IS NULL`,
      [admin.id]
    );

    await logBootstrapEvent(client, {
      adminId: admin.id,
      actorIdentifier: admin.email,
      eventType: 'admin_password_reset',
      result: 'success',
      reason: 'bootstrap_reset_password',
      metadata: {
        mode: 'reset-password',
        revoked_active_sessions: true,
      },
    });

    await client.query('COMMIT');

    console.log(
      `[BOOTSTRAP] Password redefinida para ${admin.email}; sessoes activas revogadas.`
    );
  } catch (err) {
    await client.query('ROLLBACK');
    throw err;
  } finally {
    client.release();
  }
}

async function main() {
  const { command, flags } = parseArgs(process.argv.slice(2));

  await ensureAdminSurfaceSchema();

  if (command === 'status') {
    await runStatus();
    return;
  }

  if (command === 'init') {
    await runInit();
    return;
  }

  await runResetPassword(flags.email);
}

main()
  .then(() => {
    process.exit(0);
  })
  .catch((err) => {
    console.error(`[BOOTSTRAP] Erro: ${err.message}`);
    process.exit(1);
  });
