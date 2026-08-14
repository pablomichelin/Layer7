const {
  CONTENT_SUBSCRIPTION_SKEW_SEC,
  verifyContentSubscriptionEnvelope,
} = require('./content-subscription');
const { readSecret } = require('./secret-config');

function getLicenseSigningSeedHex() {
  return readSecret('ED25519_PRIVATE_KEY', {
    required: true,
    emptyMessage: 'ED25519_PRIVATE_KEY nao configurada no ambiente',
  });
}

function decodeBase64UrlToUtf8(token) {
  if (typeof token !== 'string' || token.trim() === '') {
    return null;
  }
  const normalized = token.trim().replace(/-/g, '+').replace(/_/g, '/');
  const padLen = (4 - (normalized.length % 4)) % 4;
  const padded = normalized + '='.repeat(padLen);
  try {
    return Buffer.from(padded, 'base64').toString('utf8');
  } catch (_err) {
    return null;
  }
}

function extractContentBearerToken(req) {
  const auth = req.headers.authorization || req.headers.Authorization || '';
  if (typeof auth === 'string') {
    const m = auth.match(/^Bearer\s+(.+)$/i);
    if (m && m[1]) {
      return m[1].trim();
    }
  }
  const fallback = req.headers['x-layer7-content-token'];
  if (typeof fallback === 'string' && fallback.trim() !== '') {
    return fallback.trim();
  }
  return '';
}

/**
 * Verifica Bearer/X-Layer7-Content-Token (contrato 30.8 D7/D1–D3).
 * Retorna { ok, reason?, payload? } sem vazar o token.
 */
function verifyContentBearerToken(token, {
  nowSec = Math.floor(Date.now() / 1000),
  skewSec = CONTENT_SUBSCRIPTION_SKEW_SEC,
  seedHex = null,
} = {}) {
  if (!token) {
    return { ok: false, reason: 'missing' };
  }
  const json = decodeBase64UrlToUtf8(token);
  if (!json) {
    return { ok: false, reason: 'bearer_decode' };
  }
  let envelope;
  try {
    envelope = JSON.parse(json);
  } catch (_err) {
    return { ok: false, reason: 'envelope_json' };
  }

  const seed = seedHex || getLicenseSigningSeedHex();
  const verified = verifyContentSubscriptionEnvelope(envelope, seed);
  if (!verified.ok) {
    return { ok: false, reason: verified.reason || 'verify' };
  }

  const payload = verified.payload;
  const iat = Number(payload.iat);
  const exp = Number(payload.exp);
  if (!Number.isFinite(iat) || !Number.isFinite(exp)) {
    return { ok: false, reason: 'fields' };
  }
  const skew = Number.isFinite(skewSec) ? Math.trunc(skewSec) : CONTENT_SUBSCRIPTION_SKEW_SEC;
  if (nowSec < iat - skew) {
    return { ok: false, reason: 'iat' };
  }
  if (nowSec > exp + skew) {
    return { ok: false, reason: 'expired' };
  }

  return { ok: true, payload };
}

function contentAuthMiddleware(req, res, next) {
  const token = extractContentBearerToken(req);
  const result = verifyContentBearerToken(token);
  if (!result.ok) {
    res.set('Cache-Control', 'no-store');
    return res.status(401).json({
      error: 'content_subscription_required',
      reason: result.reason || 'unauthorized',
    });
  }
  req.contentSubscription = result.payload;
  return next();
}

module.exports = {
  decodeBase64UrlToUtf8,
  extractContentBearerToken,
  verifyContentBearerToken,
  contentAuthMiddleware,
};
