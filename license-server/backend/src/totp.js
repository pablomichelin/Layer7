/** TOTP RFC 6238 (SHA-1, 30s, 6 dígitos) — sem dependências externas. */
const crypto = require('crypto');

const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function generateTotpSecret(bytes = 20) {
  const buffer = crypto.randomBytes(bytes);
  return base32Encode(buffer);
}

function base32Encode(buffer) {
  let bits = 0;
  let value = 0;
  let output = '';

  for (const byte of buffer) {
    value = (value << 8) | byte;
    bits += 8;
    while (bits >= 5) {
      output += BASE32_ALPHABET[(value >>> (bits - 5)) & 31];
      bits -= 5;
    }
  }

  if (bits > 0) {
    output += BASE32_ALPHABET[(value << (5 - bits)) & 31];
  }

  return output;
}

function base32Decode(secret) {
  const cleaned = String(secret || '').toUpperCase().replace(/=+$/g, '').replace(/\s+/g, '');
  let bits = 0;
  let value = 0;
  const output = [];

  for (const char of cleaned) {
    const idx = BASE32_ALPHABET.indexOf(char);
    if (idx === -1) {
      throw new Error('Secret TOTP invalido.');
    }
    value = (value << 5) | idx;
    bits += 5;
    if (bits >= 8) {
      output.push((value >>> (bits - 8)) & 255);
      bits -= 8;
    }
  }

  return Buffer.from(output);
}

function hotp(secretBuffer, counter) {
  const buf = Buffer.alloc(8);
  buf.writeBigUInt64BE(BigInt(counter));
  const hmac = crypto.createHmac('sha1', secretBuffer).update(buf).digest();
  const offset = hmac[hmac.length - 1] & 0xf;
  const code = (
    ((hmac[offset] & 0x7f) << 24)
    | ((hmac[offset + 1] & 0xff) << 16)
    | ((hmac[offset + 2] & 0xff) << 8)
    | (hmac[offset + 3] & 0xff)
  ) % 1_000_000;
  return String(code).padStart(6, '0');
}

function generateTotp(secret, { step = 30, now = Date.now() } = {}) {
  const counter = Math.floor(now / 1000 / step);
  return hotp(base32Decode(secret), counter);
}

function verifyTotp(secret, token, { window = 1, step = 30, now = Date.now() } = {}) {
  const cleaned = String(token || '').replace(/\s+/g, '');
  if (!/^\d{6}$/.test(cleaned)) {
    return false;
  }

  const counter = Math.floor(now / 1000 / step);
  for (let i = -window; i <= window; i += 1) {
    if (hotp(base32Decode(secret), counter + i) === cleaned) {
      return true;
    }
  }
  return false;
}

function buildOtpauthUri({ secret, email, issuer = 'Layer7 License' }) {
  const label = encodeURIComponent(`${issuer}:${email}`);
  const params = new URLSearchParams({
    secret,
    issuer,
    algorithm: 'SHA1',
    digits: '6',
    period: '30',
  });
  return `otpauth://totp/${label}?${params.toString()}`;
}

function createTotpChallengeToken(adminId, secret) {
  if (typeof secret !== 'string' || secret.trim() === '') {
    throw new Error('TOTP challenge secret missing.');
  }

  const exp = Date.now() + (5 * 60 * 1000);
  const payload = Buffer.from(JSON.stringify({ admin_id: adminId, exp })).toString('base64url');
  const sig = crypto.createHmac('sha256', secret).update(payload).digest('base64url');
  return `${payload}.${sig}`;
}

function parseTotpChallengeToken(token, secret) {
  if (typeof secret !== 'string' || secret.trim() === '') {
    return null;
  }

  if (typeof token !== 'string' || !token.includes('.')) {
    return null;
  }
  const [payload, sig] = token.split('.');
  const expected = crypto.createHmac('sha256', secret).update(payload).digest('base64url');
  const sigBuf = Buffer.from(sig);
  const expectedBuf = Buffer.from(expected);
  if (sigBuf.length !== expectedBuf.length || !crypto.timingSafeEqual(sigBuf, expectedBuf)) {
    return null;
  }
  try {
    const data = JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
    if (!data?.admin_id || !data?.exp || Date.now() > data.exp) {
      return null;
    }
    return data;
  } catch {
    return null;
  }
}

module.exports = {
  buildOtpauthUri,
  createTotpChallengeToken,
  generateTotp,
  generateTotpSecret,
  parseTotpChallengeToken,
  verifyTotp,
};
