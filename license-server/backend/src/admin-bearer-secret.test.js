const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');
const {
  assertRequiredAuthSecrets,
  getAdminBearerJwtSecret,
  getTotpHmacSecret,
  isExplicitDevOrTestEnv,
} = require('./admin-bearer-secret');
const {
  createTotpChallengeToken,
  parseTotpChallengeToken,
} = require('./totp');

const RETIRED_TOTP_FALLBACK = 'layer7-totp-dev-secret';

test('getAdminBearerJwtSecret prefers ADMIN_BEARER_JWT_SECRET', () => {
  assert.equal(
    getAdminBearerJwtSecret({
      ADMIN_BEARER_JWT_SECRET: 'new-secret',
      JWT_SECRET: 'legacy-secret',
    }),
    'new-secret'
  );
});

test('getAdminBearerJwtSecret falls back to JWT_SECRET for upgrade compatibility', () => {
  assert.equal(
    getAdminBearerJwtSecret({
      JWT_SECRET: 'legacy-secret',
    }),
    'legacy-secret'
  );
});

test('getAdminBearerJwtSecret ignores empty values', () => {
  assert.equal(
    getAdminBearerJwtSecret({
      ADMIN_BEARER_JWT_SECRET: '   ',
      JWT_SECRET: '  ',
    }),
    ''
  );
});

test('getTotpHmacSecret never returns the retired static fallback', () => {
  assert.equal(getTotpHmacSecret({}), '');
  assert.equal(getTotpHmacSecret({
    ADMIN_BEARER_JWT_SECRET: '',
    JWT_SECRET: '   ',
  }), '');
  assert.notEqual(getTotpHmacSecret({}), RETIRED_TOTP_FALLBACK);
  assert.equal(
    getTotpHmacSecret({ ADMIN_BEARER_JWT_SECRET: 'configured-secret' }),
    'configured-secret'
  );
});

test('assertRequiredAuthSecrets refuses empty secrets in production', () => {
  assert.throws(
    () => assertRequiredAuthSecrets({ NODE_ENV: 'production' }),
    /ADMIN_BEARER_JWT_SECRET or JWT_SECRET is required/
  );
  assert.throws(
    () => assertRequiredAuthSecrets({
      NODE_ENV: 'production',
      ADMIN_BEARER_JWT_SECRET: '   ',
      JWT_SECRET: '',
    }),
    /ADMIN_BEARER_JWT_SECRET or JWT_SECRET is required/
  );
});

test('assertRequiredAuthSecrets refuses empty secrets when NODE_ENV is unset', () => {
  assert.throws(
    () => assertRequiredAuthSecrets({}),
    /ADMIN_BEARER_JWT_SECRET or JWT_SECRET is required/
  );
});

test('assertRequiredAuthSecrets accepts configured secrets in production', () => {
  assert.doesNotThrow(() => assertRequiredAuthSecrets({
    NODE_ENV: 'production',
    ADMIN_BEARER_JWT_SECRET: 'configured-secret',
  }));
  assert.doesNotThrow(() => assertRequiredAuthSecrets({
    NODE_ENV: 'production',
    JWT_SECRET: 'legacy-secret',
  }));
});

test('assertRequiredAuthSecrets allows empty secrets only in explicit development/test', () => {
  assert.equal(isExplicitDevOrTestEnv({ NODE_ENV: 'development' }), true);
  assert.equal(isExplicitDevOrTestEnv({ NODE_ENV: 'test' }), true);
  assert.equal(isExplicitDevOrTestEnv({ NODE_ENV: 'production' }), false);
  assert.equal(isExplicitDevOrTestEnv({}), false);
  assert.doesNotThrow(() => assertRequiredAuthSecrets({ NODE_ENV: 'development' }));
  assert.doesNotThrow(() => assertRequiredAuthSecrets({ NODE_ENV: 'test' }));
});

test('forged TOTP challenge signed with the retired static fallback is rejected', () => {
  const configuredSecret = 'configured-hmac-secret';
  const forged = createTotpChallengeToken(1, RETIRED_TOTP_FALLBACK);

  assert.equal(parseTotpChallengeToken(forged, configuredSecret), null);
  assert.equal(parseTotpChallengeToken(forged, getTotpHmacSecret({
    ADMIN_BEARER_JWT_SECRET: configuredSecret,
  })), null);
  assert.equal(parseTotpChallengeToken(forged, getTotpHmacSecret({})), null);
});

test('auth route source no longer embeds the retired TOTP fallback', () => {
  const authSource = fs.readFileSync(
    path.join(__dirname, 'routes', 'auth.js'),
    'utf8'
  );
  assert.equal(authSource.includes(RETIRED_TOTP_FALLBACK), false);
  assert.equal(authSource.includes("|| 'layer7-"), false);
});
