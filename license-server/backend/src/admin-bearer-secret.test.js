const test = require('node:test');
const assert = require('node:assert/strict');
const { getAdminBearerJwtSecret } = require('./admin-bearer-secret');

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
