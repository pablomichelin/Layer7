const crypto = require('crypto');

function base64urlEncode(value) {
  return Buffer.from(value)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');
}

function base64urlDecode(value) {
  const normalized = value
    .replace(/-/g, '+')
    .replace(/_/g, '/')
    .padEnd(Math.ceil(value.length / 4) * 4, '=');

  return Buffer.from(normalized, 'base64').toString('utf8');
}

function extractBearerTokenFromAuthorizationHeader(header) {
  if (typeof header !== 'string') {
    return null;
  }

  const match = header.match(/^Bearer\s+(.+)$/i);
  if (!match) {
    return null;
  }

  return match[1].trim() || null;
}

function getSessionExpirationMs(expiresAt) {
  if (expiresAt instanceof Date) {
    return expiresAt.getTime();
  }

  if (typeof expiresAt === 'string' || typeof expiresAt === 'number') {
    const parsed = new Date(expiresAt).getTime();
    if (Number.isFinite(parsed)) {
      return parsed;
    }
  }

  return null;
}

function createBearerSessionToken({
  secret,
  sessionToken,
  admin,
  session,
  issuedAtMs = Date.now(),
}) {
  if (!secret) {
    return null;
  }

  const expiresAtMs = getSessionExpirationMs(session.expires_at);
  if (!expiresAtMs) {
    return null;
  }

  const header = base64urlEncode(JSON.stringify({ alg: 'HS256', typ: 'JWT' }));
  const payload = base64urlEncode(JSON.stringify({
    typ: 'Bearer',
    id: admin.id,
    email: admin.email,
    name: admin.name,
    session_id: session.id,
    session_token: sessionToken,
    iat: Math.floor(issuedAtMs / 1000),
    exp: Math.floor(expiresAtMs / 1000),
  }));
  const signature = crypto
    .createHmac('sha256', secret)
    .update(`${header}.${payload}`)
    .digest('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');

  return `${header}.${payload}.${signature}`;
}

function verifyBearerSessionToken(token, { secret, nowMs = Date.now() } = {}) {
  if (!token || typeof token !== 'string') {
    return null;
  }

  if (!secret) {
    return null;
  }

  const parts = token.split('.');
  if (parts.length !== 3) {
    return null;
  }

  const [encodedHeader, encodedPayload, signature] = parts;
  const expectedSignature = crypto
    .createHmac('sha256', secret)
    .update(`${encodedHeader}.${encodedPayload}`)
    .digest('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');

  const expectedBuffer = Buffer.from(expectedSignature);
  const actualBuffer = Buffer.from(signature);
  if (expectedBuffer.length !== actualBuffer.length
    || !crypto.timingSafeEqual(expectedBuffer, actualBuffer)) {
    return null;
  }

  let payload;
  try {
    payload = JSON.parse(base64urlDecode(encodedPayload));
  } catch {
    return null;
  }

  if (payload.typ && payload.typ !== 'Bearer') {
    return null;
  }

  if (!payload.session_token || typeof payload.session_token !== 'string') {
    return null;
  }

  if (payload.exp && Number.isFinite(payload.exp) && nowMs >= payload.exp * 1000) {
    return null;
  }

  return payload.session_token;
}

module.exports = {
  createBearerSessionToken,
  extractBearerTokenFromAuthorizationHeader,
  verifyBearerSessionToken,
};
