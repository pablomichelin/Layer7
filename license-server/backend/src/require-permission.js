const { hasPermission } = require('./admin-permissions');
const { createHttpError } = require('./crud-integrity');

function requirePermission(permissionKey) {
  return function requirePermissionMiddleware(req, res, next) {
    if (!req.admin) {
      return res.status(401).json({ error: 'Autenticacao obrigatoria.' });
    }

    if (!hasPermission(req.admin, permissionKey)) {
      return res.status(403).json({ error: 'Sem permissao para esta acao.' });
    }

    return next();
  };
}

function assertPermission(admin, permissionKey) {
  if (!hasPermission(admin, permissionKey)) {
    throw createHttpError(403, 'Sem permissao para esta acao.');
  }
}

module.exports = {
  assertPermission,
  requirePermission,
};
