const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { ensureTotpSchema } = require('./totp-schema');

test('P0-2 — ensureTotpSchema cria admin_totp_challenges com jti único', async () => {
  const statements = [];
  await ensureTotpSchema({
    async query(sql) {
      statements.push(String(sql));
      return { rows: [] };
    },
  });

  const sql = statements.join('\n');
  assert.match(sql, /CREATE TABLE IF NOT EXISTS admin_totp_challenges/);
  assert.match(sql, /jti VARCHAR\(64\) PRIMARY KEY/);
  assert.match(sql, /admin_id INTEGER NOT NULL REFERENCES admins\(id\)/);
  assert.match(sql, /expires_at TIMESTAMPTZ NOT NULL/);
  assert.match(sql, /used_at TIMESTAMPTZ/);
  assert.match(sql, /idx_admin_totp_challenges_admin/);
});

test('P0-2 — 001-init.sql espelha admin_totp_challenges para installs novas', () => {
  const initSql = fs.readFileSync(
    path.join(__dirname, '..', 'migrations', '001-init.sql'),
    'utf8'
  );
  assert.match(initSql, /CREATE TABLE admin_totp_challenges/);
  assert.match(initSql, /jti\s+VARCHAR\(64\) PRIMARY KEY/);
  assert.match(initSql, /used_at\s+TIMESTAMPTZ/);
  assert.match(initSql, /idx_admin_totp_challenges_admin/);
  assert.equal(
    (initSql.match(/CREATE TABLE admin_totp_challenges/g) || []).length,
    1
  );
});
