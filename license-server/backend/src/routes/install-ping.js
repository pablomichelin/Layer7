const { Router } = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../db');
const { isHttpError } = require('../crud-integrity');
const { getClientIp } = require('../session');
const {
  parseInstallPingPayload,
  shouldSkipHeartbeatLog,
} = require('../install-ping-parse');
const { UPSERT_INSTALL_SQL, upsertParams } = require('../install-ping-schema');

const router = Router();

const installPingLimiter = rateLimit({
  windowMs: 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  message: { error: 'Demasiadas tentativas. Tente novamente em 1 minuto.' },
});

router.post('/license/install-ping', installPingLimiter, async (req, res) => {
  const ip = getClientIp(req);
  const ua = req.headers['user-agent'] || '';
  let parsed;

  try {
    const rawLength = typeof req.body === 'object'
      ? JSON.stringify(req.body).length
      : 0;
    parsed = parseInstallPingPayload(req.body, { rawLength });

    const previous = await pool.query(
      `SELECT id, last_seen_at FROM install_instances WHERE hardware_id = $1`,
      [parsed.hardwareId]
    );
    const skipLog = shouldSkipHeartbeatLog(
      previous.rows[0]?.last_seen_at,
      parsed.event
    );

    const upserted = await pool.query(
      UPSERT_INSTALL_SQL,
      upsertParams(parsed, ip, ua)
    );
    const instance = upserted.rows[0];

    if (!skipLog) {
      await pool.query(
        `INSERT INTO install_pings_log
           (instance_id, hardware_id, event, package_version, egress_ip, user_agent, result)
         VALUES ($1, $2, $3, $4, $5, $6, 'ok')`,
        [
          instance.id,
          parsed.hardwareId,
          parsed.event,
          parsed.packageVersion,
          ip,
          ua ? String(ua).slice(0, 255) : null,
        ]
      );
    }

    return res.json({ status: 'ok' });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[INSTALL-PING] Error:', error.message);
    return res.status(500).json({ error: 'Erro interno.' });
  }
});

module.exports = router;
