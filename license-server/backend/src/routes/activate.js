const { Router } = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../db');
const { generateSignedLicense } = require('../crypto');

const router = Router();

const activateLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Demasiadas tentativas. Tente novamente em 1 minuto.' },
});

async function logActivation(licenseId, hardwareId, ip, ua, result, errorMessage) {
  try {
    await pool.query(
      `INSERT INTO activations_log (license_id, hardware_id, ip_address, user_agent, result, error_message)
       VALUES ($1, $2, $3, $4, $5, $6)`,
      [licenseId, hardwareId, ip, ua, result, errorMessage]
    );
  } catch (err) {
    console.error('[ACTIVATE] Log error:', err.message);
  }
}

router.post('/activate', activateLimiter, async (req, res) => {
  const { key, hardware_id } = req.body;
  const ip = req.headers['x-real-ip'] || req.headers['x-forwarded-for'] || req.ip;
  const ua = req.headers['user-agent'] || '';

  if (!key || !hardware_id) {
    return res.status(400).json({ error: 'key e hardware_id obrigatorios' });
  }

  try {
    const result = await pool.query(
      `SELECT l.*, c.name AS customer_name
       FROM licenses l
       LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.license_key = $1`,
      [key]
    );

    if (result.rows.length === 0) {
      await logActivation(null, hardware_id, ip, ua, 'fail', 'License key not found');
      return res.status(404).json({ error: 'Licenca nao encontrada' });
    }

    const lic = result.rows[0];

    if (lic.status === 'revoked') {
      await logActivation(lic.id, hardware_id, ip, ua, 'revoked', 'License revoked');
      return res.status(403).json({ error: 'Licenca revogada' });
    }

    if (lic.status === 'expired' || new Date(lic.expiry) < new Date()) {
      await logActivation(lic.id, hardware_id, ip, ua, 'fail', 'License expired');
      return res.status(403).json({ error: 'Licenca expirada' });
    }

    if (!lic.hardware_id) {
      await pool.query(
        'UPDATE licenses SET hardware_id = $1, activated_at = NOW(), updated_at = NOW() WHERE id = $2',
        [hardware_id, lic.id]
      );
    } else if (lic.hardware_id !== hardware_id) {
      await logActivation(lic.id, hardware_id, ip, ua, 'fail', 'Hardware ID mismatch');
      return res.status(403).json({ error: 'Hardware ID nao corresponde' });
    }

    const signed = generateSignedLicense({
      hardware_id,
      expiry: new Date(lic.expiry).toISOString().slice(0, 10),
      customer: lic.customer_name || 'Unknown',
      features: lic.features || 'full',
    });

    if (!lic.activated_at) {
      await pool.query(
        'UPDATE licenses SET activated_at = NOW(), updated_at = NOW() WHERE id = $1',
        [lic.id]
      );
    }

    await logActivation(lic.id, hardware_id, ip, ua, 'success', null);

    res.json(signed);
  } catch (err) {
    console.error('[ACTIVATE] Error:', err.message);
    res.status(500).json({ error: 'Erro interno na activacao' });
  }
});

module.exports = router;
