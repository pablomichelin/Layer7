const { Router } = require('express');
const bcrypt = require('bcryptjs');
const pool = require('../db');
const auth = require('../auth');
const {
  clearSessionCookie,
  createSession,
  getSessionTokenFromRequest,
  requireSecureSessionRequest,
  revokeSessionByToken,
  setSessionCookie,
  toSessionResponsePayload,
} = require('../session');

const router = Router();

router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body;
    if (!email || !password) {
      return res.status(400).json({ error: 'Email e password obrigatorios' });
    }
    if (!requireSecureSessionRequest(req)) {
      clearSessionCookie(res);
      return res.status(400).json({ error: 'Login administrativo exige HTTPS/TLS oficial' });
    }

    const result = await pool.query('SELECT * FROM admins WHERE email = $1', [email]);
    if (result.rows.length === 0) {
      return res.status(401).json({ error: 'Credenciais invalidas' });
    }

    const admin = result.rows[0];
    const valid = await bcrypt.compare(password, admin.password_hash);
    if (!valid) {
      return res.status(401).json({ error: 'Credenciais invalidas' });
    }

    const session = await createSession(admin, req);
    setSessionCookie(res, session.token, session.metadata.session.expires_at);
    res.setHeader('Cache-Control', 'no-store');
    res.json(toSessionResponsePayload(session.metadata));
  } catch (err) {
    console.error('[AUTH] Login error:', err.message);
    res.status(500).json({ error: 'Erro interno' });
  }
});

router.get('/session', auth, async (req, res) => {
  res.setHeader('Cache-Control', 'no-store');
  res.json(toSessionResponsePayload({
    admin: req.admin,
    session: req.adminSession,
  }));
});

router.post('/logout', async (req, res) => {
  try {
    const token = getSessionTokenFromRequest(req);
    await revokeSessionByToken(token);
    clearSessionCookie(res);
    res.setHeader('Cache-Control', 'no-store');
    res.json({ message: 'Sessao encerrada' });
  } catch (err) {
    console.error('[AUTH] Logout error:', err.message);
    clearSessionCookie(res);
    res.status(500).json({ error: 'Erro interno' });
  }
});

module.exports = router;
