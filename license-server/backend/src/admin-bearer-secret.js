const DEV_TEST_NODE_ENVS = new Set(['development', 'test']);

function getAdminBearerJwtSecret(env = process.env) {
  if (!env || typeof env !== 'object') {
    return '';
  }

  const primary = typeof env.ADMIN_BEARER_JWT_SECRET === 'string'
    ? env.ADMIN_BEARER_JWT_SECRET.trim()
    : '';

  if (primary) {
    return primary;
  }

  const legacy = typeof env.JWT_SECRET === 'string'
    ? env.JWT_SECRET.trim()
    : '';

  return legacy || '';
}

function getTotpHmacSecret(env = process.env) {
  return getAdminBearerJwtSecret(env);
}

function isExplicitDevOrTestEnv(env = process.env) {
  if (!env || typeof env !== 'object') {
    return false;
  }

  const nodeEnv = typeof env.NODE_ENV === 'string' ? env.NODE_ENV.trim() : '';
  return DEV_TEST_NODE_ENVS.has(nodeEnv);
}

function assertRequiredAuthSecrets(env = process.env) {
  if (getAdminBearerJwtSecret(env)) {
    return;
  }

  if (isExplicitDevOrTestEnv(env)) {
    return;
  }

  throw new Error(
    'ADMIN_BEARER_JWT_SECRET or JWT_SECRET is required outside explicit development/test'
  );
}

module.exports = {
  assertRequiredAuthSecrets,
  getAdminBearerJwtSecret,
  getTotpHmacSecret,
  isExplicitDevOrTestEnv,
};
