const test = require('node:test');
const assert = require('node:assert/strict');
const {
  generateTotp,
  generateTotpSecret,
  verifyTotp,
  createTotpChallengeToken,
  parseTotpChallengeToken,
} = require('./totp');

test('TOTP generate/verify roundtrip', () => {
  const secret = generateTotpSecret();
  const now = Date.now();
  const token = generateTotp(secret, { now });
  assert.equal(verifyTotp(secret, token, { now }), true);
  assert.equal(verifyTotp(secret, '000000', { now }), false);
});

test('TOTP challenge token roundtrip', () => {
  const secret = 'test-hmac-secret';
  const token = createTotpChallengeToken(42, secret);
  const parsed = parseTotpChallengeToken(token, secret);
  assert.equal(parsed.admin_id, 42);
  assert.ok(parsed.exp > Date.now());
});
