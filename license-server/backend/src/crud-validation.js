const { createHttpError } = require('./http-error');

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_PATTERN = /^[0-9+()\-\s]{7,32}$/;
const LICENSE_KEY_PATTERN = /^[a-f0-9]{32}$/i;
const HARDWARE_ID_PATTERN = /^[a-f0-9]{64}$/i;
const FEATURE_TOKEN_PATTERN = /^[a-z0-9][a-z0-9_-]{0,31}$/i;
const LICENSE_STATUSES = new Set(['active', 'expired', 'revoked']);
/** ADR-0025 P1: CSV features ≤ 63 bytes úteis (buffer daemon 64 com NUL). */
const FEATURES_MAX_BYTES = 63;
/** Tokens emitíveis (SKU X/Y + legado full). */
const KNOWN_FEATURE_TOKENS = new Set(['base', 'identity', 'mitm', 'full']);
const FEATURE_CANONICAL_ORDER = ['base', 'identity', 'mitm'];

function ensureObject(payload, message = 'Payload invalido.') {
  if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw createHttpError(400, message);
  }

  return payload;
}

function rejectUnexpectedFields(payload, allowedFields, subject = 'Payload') {
  const unexpectedFields = Object.keys(payload).filter((field) => !allowedFields.includes(field));

  if (unexpectedFields.length > 0) {
    throw createHttpError(400, `${subject} contem campos nao permitidos: ${unexpectedFields.join(', ')}`);
  }
}

function assertNonEmptyPayload(payload, message = 'Nenhum campo valido informado.') {
  if (Object.keys(payload).length === 0) {
    throw createHttpError(400, message);
  }
}

function normalizeOptionalText(value, fieldName, maxLength) {
  if (value === undefined) {
    return undefined;
  }

  if (value === null) {
    return null;
  }

  if (typeof value !== 'string') {
    throw createHttpError(400, `${fieldName} invalido.`);
  }

  const normalized = value.trim();
  if (!normalized) {
    return null;
  }

  if (normalized.length > maxLength) {
    throw createHttpError(400, `${fieldName} excede o tamanho maximo permitido.`);
  }

  return normalized;
}

function normalizeRequiredText(value, fieldName, maxLength) {
  if (typeof value !== 'string') {
    throw createHttpError(400, `${fieldName} obrigatorio.`);
  }

  const normalized = value.trim();
  if (!normalized) {
    throw createHttpError(400, `${fieldName} obrigatorio.`);
  }

  if (normalized.length > maxLength) {
    throw createHttpError(400, `${fieldName} excede o tamanho maximo permitido.`);
  }

  return normalized;
}

function normalizeStoredHardwareId(value) {
  if (typeof value !== 'string') {
    return null;
  }

  const normalized = value.trim().toLowerCase();
  if (!normalized || !HARDWARE_ID_PATTERN.test(normalized)) {
    return null;
  }

  return normalized;
}

function normalizeEmail(value) {
  if (value === undefined) {
    return undefined;
  }

  const normalized = normalizeOptionalText(value, 'Email', 255);
  if (normalized === null) {
    return null;
  }

  if (!EMAIL_PATTERN.test(normalized)) {
    throw createHttpError(400, 'Email invalido.');
  }

  return normalized.toLowerCase();
}

function normalizePhone(value) {
  if (value === undefined) {
    return undefined;
  }

  const normalized = normalizeOptionalText(value, 'Telefone', 32);
  if (normalized === null) {
    return null;
  }

  if (!PHONE_PATTERN.test(normalized)) {
    throw createHttpError(400, 'Telefone invalido.');
  }

  return normalized;
}

function normalizePositiveInt(value, fieldName) {
  if (typeof value === 'number') {
    if (!Number.isInteger(value) || value <= 0) {
      throw createHttpError(400, `${fieldName} invalido.`);
    }

    return value;
  }

  if (typeof value !== 'string') {
    throw createHttpError(400, `${fieldName} invalido.`);
  }

  const normalized = value.trim();
  if (!/^\d+$/.test(normalized)) {
    throw createHttpError(400, `${fieldName} invalido.`);
  }

  const parsed = Number.parseInt(normalized, 10);
  if (!Number.isSafeInteger(parsed) || parsed <= 0) {
    throw createHttpError(400, `${fieldName} invalido.`);
  }

  return parsed;
}

function parseIdParam(value, fieldName = 'ID') {
  return normalizePositiveInt(value, fieldName);
}

function parseIsoDate(value, fieldName) {
  if (typeof value !== 'string') {
    throw createHttpError(400, `${fieldName} invalida.`);
  }

  const normalized = value.trim();
  if (!/^\d{4}-\d{2}-\d{2}$/.test(normalized)) {
    throw createHttpError(400, `${fieldName} invalida.`);
  }

  const parsed = new Date(`${normalized}T00:00:00.000Z`);
  if (Number.isNaN(parsed.getTime()) || parsed.toISOString().slice(0, 10) !== normalized) {
    throw createHttpError(400, `${fieldName} invalida.`);
  }

  return normalized;
}

function normalizeFeatures(value, { defaultValue = 'base' } = {}) {
  const rawValue = value === undefined || value === null || value === ''
    ? defaultValue
    : value;
  let tokens = [];

  if (Array.isArray(rawValue)) {
    tokens = rawValue;
  } else if (typeof rawValue === 'string') {
    if (Buffer.byteLength(rawValue, 'utf8') > FEATURES_MAX_BYTES) {
      throw createHttpError(400,
        `Features excedem ${FEATURES_MAX_BYTES} bytes (ADR-0025 P1).`);
    }
    tokens = rawValue.split(',').map((token) => token.trim()).filter(Boolean);
  } else {
    throw createHttpError(400, 'Features invalidas.');
  }

  if (tokens.length === 0 || tokens.length > 10) {
    throw createHttpError(400, 'Features invalidas.');
  }

  const seen = new Set();
  for (const token of tokens) {
    if (typeof token !== 'string') {
      throw createHttpError(400, 'Features invalidas.');
    }

    const normalizedToken = token.trim().toLowerCase();
    if (!FEATURE_TOKEN_PATTERN.test(normalizedToken)) {
      throw createHttpError(400, 'Features invalidas.');
    }
    if (!KNOWN_FEATURE_TOKENS.has(normalizedToken)) {
      throw createHttpError(400,
        `Features invalidas: token desconhecido "${normalizedToken}". `
        + 'Permitidos: base, identity, mitm (legado: full).');
    }
    seen.add(normalizedToken);
  }

  /*
   * T1 / emissão: "full" é alias de base. Se há identity/mitm explícitos,
   * normalizar para CSV canónico sem "full". Se só full → "base".
   */
  const hasAddon = seen.has('identity') || seen.has('mitm');
  const ordered = [];

  if (hasAddon || seen.has('base') || seen.has('full') || seen.size === 0) {
    ordered.push('base');
  }
  for (const token of FEATURE_CANONICAL_ORDER) {
    if (token === 'base') {
      continue;
    }
    if (seen.has(token)) {
      ordered.push(token);
    }
  }

  const normalized = ordered.join(',');
  if (Buffer.byteLength(normalized, 'utf8') > FEATURES_MAX_BYTES) {
    throw createHttpError(400,
      `Features excedem ${FEATURES_MAX_BYTES} bytes (ADR-0025 P1).`);
  }

  return normalized;
}

function parsePaginationQuery(query, allowedFields) {
  const payload = ensureObject(query, 'Query invalida.');
  rejectUnexpectedFields(payload, allowedFields, 'Query');

  const page = query.page === undefined ? 1 : normalizePositiveInt(query.page, 'page');
  const limit = query.limit === undefined ? 20 : normalizePositiveInt(query.limit, 'limit');

  if (limit > 200) {
    throw createHttpError(400, 'limit excede o maximo permitido.');
  }

  return {
    page,
    limit,
    offset: (page - 1) * limit,
  };
}

function parseSearch(value) {
  if (value === undefined) {
    return undefined;
  }

  return normalizeOptionalText(value, 'Busca', 120);
}

function parseCustomersListQuery(query) {
  const { page, limit, offset } = parsePaginationQuery(query, ['search', 'page', 'limit']);

  return {
    page,
    limit,
    offset,
    search: parseSearch(query.search),
  };
}

function parseBoundFilter(value) {
  if (value === undefined) {
    return undefined;
  }

  if (typeof value !== 'string') {
    throw createHttpError(400, 'bound invalido.');
  }

  const normalized = value.trim().toLowerCase();
  if (normalized === 'yes' || normalized === 'true' || normalized === '1') {
    return true;
  }
  if (normalized === 'no' || normalized === 'false' || normalized === '0') {
    return false;
  }

  throw createHttpError(400, 'bound invalido.');
}

function parseExpiringWithinDays(value) {
  if (value === undefined || value === null || value === '') {
    return undefined;
  }

  const days = normalizePositiveInt(value, 'expiring_within_days');
  if (days > 365) {
    throw createHttpError(400, 'expiring_within_days excede o maximo permitido.');
  }

  return days;
}

function parseLicensesListQuery(query) {
  const { page, limit, offset } = parsePaginationQuery(query, [
    'status',
    'customer_id',
    'search',
    'page',
    'limit',
    'bound',
    'expiring_within_days',
  ]);
  let status;
  let customerId;

  if (query.status !== undefined) {
    if (typeof query.status !== 'string') {
      throw createHttpError(400, 'status invalido.');
    }

    status = query.status.trim().toLowerCase();
    if (!LICENSE_STATUSES.has(status)) {
      throw createHttpError(400, 'status invalido.');
    }
  }

  if (query.customer_id !== undefined) {
    customerId = normalizePositiveInt(query.customer_id, 'customer_id');
  }

  return {
    page,
    limit,
    offset,
    search: parseSearch(query.search),
    status,
    customerId,
    bound: parseBoundFilter(query.bound),
    expiringWithinDays: parseExpiringWithinDays(query.expiring_within_days),
  };
}

function parseCustomerCreatePayload(body) {
  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ['name', 'email', 'phone', 'notes']);

  return {
    name: normalizeRequiredText(payload.name, 'Nome', 255),
    email: normalizeEmail(payload.email),
    phone: normalizePhone(payload.phone),
    notes: normalizeOptionalText(payload.notes, 'Notas', 2000),
  };
}

function parseCustomerUpdatePayload(body) {
  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ['name', 'email', 'phone', 'notes']);
  assertNonEmptyPayload(payload);

  const normalized = {};

  if (Object.prototype.hasOwnProperty.call(payload, 'name')) {
    normalized.name = normalizeRequiredText(payload.name, 'Nome', 255);
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'email')) {
    normalized.email = normalizeEmail(payload.email);
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'phone')) {
    normalized.phone = normalizePhone(payload.phone);
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'notes')) {
    normalized.notes = normalizeOptionalText(payload.notes, 'Notas', 2000);
  }

  return normalized;
}

function parseLicenseCreatePayload(body) {
  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ['customer_id', 'expiry', 'features', 'notes']);

  if (!Object.prototype.hasOwnProperty.call(payload, 'customer_id')) {
    throw createHttpError(400, 'customer_id obrigatorio.');
  }
  if (!Object.prototype.hasOwnProperty.call(payload, 'expiry')) {
    throw createHttpError(400, 'expiry obrigatoria.');
  }

  return {
    customerId: normalizePositiveInt(payload.customer_id, 'customer_id'),
    expiry: parseIsoDate(payload.expiry, 'Data de expiracao'),
    features: normalizeFeatures(payload.features),
    notes: normalizeOptionalText(payload.notes, 'Notas', 2000),
  };
}

function parseLicenseUpdatePayload(body) {
  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ['customer_id', 'expiry', 'features', 'notes']);
  assertNonEmptyPayload(payload);

  const normalized = {};

  if (Object.prototype.hasOwnProperty.call(payload, 'customer_id')) {
    normalized.customerId = normalizePositiveInt(payload.customer_id, 'customer_id');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'expiry')) {
    normalized.expiry = parseIsoDate(payload.expiry, 'Data de expiracao');
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'features')) {
    normalized.features = normalizeFeatures(payload.features, { defaultValue: 'base' });
  }
  if (Object.prototype.hasOwnProperty.call(payload, 'notes')) {
    normalized.notes = normalizeOptionalText(payload.notes, 'Notas', 2000);
  }

  return normalized;
}

function parseActivatePayload(body) {
  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, ['key', 'hardware_id']);

  const key = normalizeRequiredText(payload.key, 'key', 32).toLowerCase();
  const hardwareId = normalizeStoredHardwareId(
    normalizeRequiredText(payload.hardware_id, 'hardware_id', 64)
  );

  if (!LICENSE_KEY_PATTERN.test(key)) {
    throw createHttpError(400, 'key invalida.');
  }
  if (!hardwareId) {
    throw createHttpError(400, 'hardware_id invalido.');
  }

  return {
    key,
    hardwareId,
  };
}

function assertEmptyBody(body) {
  if (body === undefined || body === null) {
    return;
  }

  const payload = ensureObject(body);
  rejectUnexpectedFields(payload, []);
}

function normalizeExpiryDate(expiry) {
  if (!expiry) {
    return null;
  }

  if (expiry instanceof Date) {
    if (Number.isNaN(expiry.getTime())) {
      return null;
    }

    return expiry.toISOString().slice(0, 10);
  }

  if (typeof expiry === 'string') {
    const trimmed = expiry.trim();
    if (!trimmed) {
      return null;
    }

    const match = trimmed.match(/^(\d{4}-\d{2}-\d{2})/);
    return match ? match[1] : null;
  }

  return null;
}

function isLicenseExpired(license) {
  const expiry = normalizeExpiryDate(license?.expiry);
  if (!expiry) {
    return false;
  }

  const today = new Date().toISOString().slice(0, 10);
  return expiry < today;
}

module.exports = {
  assertEmptyBody,
  FEATURES_MAX_BYTES,
  isLicenseExpired,
  normalizeFeatures,
  normalizeStoredHardwareId,
  parseActivatePayload,
  parseCustomerCreatePayload,
  parseCustomerUpdatePayload,
  parseCustomersListQuery,
  parseIdParam,
  parseLicenseCreatePayload,
  parseLicenseUpdatePayload,
  parseLicensesListQuery,
};
