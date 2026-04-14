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

module.exports = {
  getAdminBearerJwtSecret,
};
