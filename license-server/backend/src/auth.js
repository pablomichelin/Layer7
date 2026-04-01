const { clearSessionCookie, resolveSession } = require('./session');

async function authMiddleware(req, res, next) {
  try {
    const session = await resolveSession(req, res);
    if (!session) {
      clearSessionCookie(res);
      return res.status(401).json({ error: 'Sessao invalida ou expirada' });
    }

    req.admin = session.metadata.admin;
    req.adminSession = session.metadata.session;
    next();
  } catch (err) {
    console.error('[AUTH] Session validation error:', err.message);
    clearSessionCookie(res);
    return res.status(500).json({ error: 'Erro interno' });
  }
}

module.exports = authMiddleware;
