const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');

const router = Router();
router.use(auth);

router.get('/', async (_req, res) => {
  try {
    const stats = await pool.query(`
      SELECT
        COUNT(*) FILTER (WHERE archived_at IS NULL AND status = 'active' AND expiry >= CURRENT_DATE) AS active,
        COUNT(*) FILTER (
          WHERE archived_at IS NULL
            AND (status = 'expired' OR (status = 'active' AND expiry < CURRENT_DATE))
        ) AS expired,
        COUNT(*) FILTER (WHERE archived_at IS NULL AND status = 'revoked') AS revoked,
        COUNT(*) FILTER (WHERE archived_at IS NULL) AS total
      FROM licenses
    `);

    const customers = await pool.query(`
      SELECT COUNT(*) AS total
        FROM customers
       WHERE archived_at IS NULL
    `);

    const activations24h = await pool.query(
      `SELECT COUNT(*)
         FROM activations_log al
         LEFT JOIN licenses l ON l.id = al.license_id
        WHERE al.created_at > NOW() - INTERVAL '24 hours'
          AND (l.id IS NULL OR l.archived_at IS NULL)`
    );

    const recentActivations = await pool.query(`
      SELECT al.created_at, al.result, al.ip_address, al.hardware_id,
             c.name AS customer_name, l.license_key
      FROM activations_log al
      LEFT JOIN licenses l ON l.id = al.license_id
      LEFT JOIN customers c ON c.id = l.customer_id
      WHERE l.id IS NULL OR (l.archived_at IS NULL AND (c.id IS NULL OR c.archived_at IS NULL))
      ORDER BY al.created_at DESC
      LIMIT 10
    `);

    res.json({
      licenses: {
        active: parseInt(stats.rows[0].active),
        expired: parseInt(stats.rows[0].expired),
        revoked: parseInt(stats.rows[0].revoked),
        total: parseInt(stats.rows[0].total),
      },
      customers: parseInt(customers.rows[0].total, 10),
      activations_24h: parseInt(activations24h.rows[0].count),
      recent_activations: recentActivations.rows,
    });
  } catch (err) {
    console.error('[DASHBOARD] Error:', err.message);
    res.status(500).json({ error: 'Erro ao carregar dashboard' });
  }
});

module.exports = router;
