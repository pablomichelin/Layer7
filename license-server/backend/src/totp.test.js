const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('crypto');
const {
  generateTotp,
  generateTotpSecret,
  verifyTotp,
  timingSafeEqualUtf8,
  createTotpChallengeToken,
  parseTotpChallengeToken,
} = require('./totp');

function assertVerifyReturnsFalse(secret, token, now) {
  assert.equal(verifyTotp(secret, token, { now }), false);
}

test('P3-3C — codigo valido no mesmo now devolve true', () => {
  const secret = generateTotpSecret();
  const now = 1_776_000_000_000;
  const token = generateTotp(secret, { now });
  assert.equal(token.length, 6);
  assert.match(token, /^\d{6}$/);
  assert.equal(verifyTotp(secret, token, { now }), true);
});

test('P3-3C — codigo invalido de 6 digitos devolve false', () => {
  const secret = generateTotpSecret();
  const now = 1_776_000_000_000;
  const token = generateTotp(secret, { now });
  const invalid = token === '000000' ? '000001' : '000000';
  assert.equal(verifyTotp(secret, invalid, { now }), false);
});

test('P3-3C — malformed devolve false sem throw', () => {
  const secret = generateTotpSecret();
  const now = 1_776_000_000_000;

  assert.doesNotThrow(() => verifyTotp(secret, '', { now }));
  assert.doesNotThrow(() => verifyTotp(secret, null, { now }));
  assert.doesNotThrow(() => verifyTotp(secret, undefined, { now }));
  assert.doesNotThrow(() => verifyTotp(secret, '12345', { now }));
  assert.doesNotThrow(() => verifyTotp(secret, '1234567', { now }));
  assert.doesNotThrow(() => verifyTotp(secret, 'abcdef', { now }));
  assert.doesNotThrow(() => verifyTotp(secret, '12a456', { now }));

  assertVerifyReturnsFalse(secret, '', now);
  assertVerifyReturnsFalse(secret, null, now);
  assertVerifyReturnsFalse(secret, undefined, now);
  assertVerifyReturnsFalse(secret, '12345', now);
  assertVerifyReturnsFalse(secret, '1234567', now);
  assertVerifyReturnsFalse(secret, 'abcdef', now);
  assertVerifyReturnsFalse(secret, '12a456', now);
});

test('P3-3C — timingSafeEqual sem guarda de comprimento lanca RangeError', () => {
  assert.throws(
    () => crypto.timingSafeEqual(
      Buffer.from('123456', 'utf8'),
      Buffer.from('12345', 'utf8')
    ),
    { name: 'RangeError' }
  );
});

test('P3-3C — guarda de comprimento evita RangeError e trata mismatch', () => {
  const source = require('node:fs').readFileSync(require.resolve('./totp'), 'utf8');
  assert.match(
    source,
    /if \(leftBuf\.length !== rightBuf\.length\) \{\s*return false;\s*\}/,
    'mismatch de comprimento tem de regressar antes de timingSafeEqual'
  );
  assert.match(source, /crypto\.timingSafeEqual\(leftBuf, rightBuf\)/);
  assert.match(source, /timingSafeEqualUtf8\(hotp\(base32Decode\(secret\), counter \+ i\), cleaned\)/);
  assert.doesNotMatch(source, /hotp\([^)]+\) === cleaned/);
  assert.doesNotThrow(() => timingSafeEqualUtf8('123456', '12345'));
  assert.doesNotThrow(() => timingSafeEqualUtf8('12345', '1234567'));
  assert.equal(timingSafeEqualUtf8('123456', '12345'), false);
  assert.equal(timingSafeEqualUtf8('12345', '1234567'), false);
  assert.equal(timingSafeEqualUtf8('123456', '123456'), true);
  assert.equal(timingSafeEqualUtf8('123456', '654321'), false);
});

test('TOTP challenge token roundtrip', () => {
  const secret = 'test-hmac-secret';
  const token = createTotpChallengeToken(42, secret);
  const parsed = parseTotpChallengeToken(token, secret);
  assert.equal(parsed.admin_id, 42);
  assert.ok(parsed.exp > Date.now());
  assert.match(parsed.jti, /^[0-9a-f]{32}$/);
});

test('P0-2 — cada challenge traz jti aleatório e rejeita payload antigo sem jti', () => {
  const secret = 'test-hmac-secret';
  const first = parseTotpChallengeToken(createTotpChallengeToken(7, secret), secret);
  const second = parseTotpChallengeToken(createTotpChallengeToken(7, secret), secret);
  assert.notEqual(first.jti, second.jti);

  const payload = Buffer.from(JSON.stringify({
    admin_id: 7,
    exp: Date.now() + 60_000,
  })).toString('base64url');
  const sig = crypto.createHmac('sha256', secret).update(payload).digest('base64url');
  assert.equal(parseTotpChallengeToken(`${payload}.${sig}`, secret), null);
});

test('TOTP challenge helpers refuse an empty HMAC secret', () => {
  assert.throws(
    () => createTotpChallengeToken(1, ''),
    /TOTP challenge secret missing/
  );
  assert.throws(
    () => createTotpChallengeToken(1, '   '),
    /TOTP challenge secret missing/
  );
  assert.equal(parseTotpChallengeToken('payload.sig', ''), null);
  assert.equal(parseTotpChallengeToken('payload.sig', '   '), null);
});
