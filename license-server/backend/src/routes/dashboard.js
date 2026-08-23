const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');
const {
  LICENSE_SQL_ACTIVE_CONDITION,
  LICENSE_SQL_EXPIRED_CONDITION,
  LICENSE_SQL_REVOKED_CONDITION,
} = require('../license-state');
const { requirePermission } = require('../require-permission');
const { collectMultiApplianceAlerts } = require('../multi-appliance-abuse-query');

const router = Router();
router.use(auth);
router.use(requirePermission('dashboard.read'));

const SAMPLE_LIMIT = 8;

router.get('/', async (_req, res) => {
  try {
    const stats = await pool.query(`
      SELECT
        COUNT(*) FILTER (WHERE archived_at IS NULL AND ${LICENSE_SQL_ACTIVE_CONDITION}) AS active,
        COUNT(*) FILTER (
          WHERE archived_at IS NULL
            AND ${LICENSE_SQL_EXPIRED_CONDITION}
        ) AS expired,
        COUNT(*) FILTER (WHERE archived_at IS NULL AND ${LICENSE_SQL_REVOKED_CONDITION}) AS revoked,
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

    const expiring30d = await pool.query(`
      SELECT COUNT(*) AS total
        FROM licenses
       WHERE archived_at IS NULL
         AND ${LICENSE_SQL_ACTIVE_CONDITION}
         AND expiry <= CURRENT_DATE + INTERVAL '30 days'
    `);

    const expiredBound = await pool.query(`
      SELECT COUNT(*) AS total
        FROM licenses
       WHERE archived_at IS NULL
         AND ${LICENSE_SQL_EXPIRED_CONDITION}
         AND hardware_id IS NOT NULL
         AND btrim(hardware_id) <> ''
    `);

    const unboundStale = await pool.query(`
      SELECT COUNT(*) AS total
        FROM licenses
       WHERE archived_at IS NULL
         AND ${LICENSE_SQL_ACTIVE_CONDITION}
         AND (hardware_id IS NULL OR btrim(hardware_id) = '')
         AND created_at < NOW() - INTERVAL '7 days'
    `);

    const staleCheckin = await pool.query(`
      SELECT COUNT(*) AS total
        FROM licenses l
        LEFT JOIN LATERAL (
          SELECT MAX(created_at) AS last_check_in_at
            FROM check_ins_log
           WHERE license_id = l.id
        ) ci ON TRUE
       WHERE l.archived_at IS NULL
         AND l.hardware_id IS NOT NULL
         AND btrim(l.hardware_id) <> ''
         AND (ci.last_check_in_at IS NULL OR ci.last_check_in_at < NOW() - INTERVAL '7 days')
    `);

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

    const sampleExpiring = await pool.query(`
      SELECT l.id, l.license_key, l.expiry, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.archived_at IS NULL
         AND ${LICENSE_SQL_ACTIVE_CONDITION}
         AND l.expiry <= CURRENT_DATE + INTERVAL '30 days'
         AND (c.id IS NULL OR c.archived_at IS NULL)
       ORDER BY l.expiry ASC
       LIMIT ${SAMPLE_LIMIT}
    `);

    const sampleExpiredBound = await pool.query(`
      SELECT l.id, l.license_key, l.expiry, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.archived_at IS NULL
         AND ${LICENSE_SQL_EXPIRED_CONDITION}
         AND l.hardware_id IS NOT NULL
         AND btrim(l.hardware_id) <> ''
         AND (c.id IS NULL OR c.archived_at IS NULL)
       ORDER BY l.expiry DESC
       LIMIT ${SAMPLE_LIMIT}
    `);

    const sampleUnbound = await pool.query(`
      SELECT l.id, l.license_key, l.created_at, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
       WHERE l.archived_at IS NULL
         AND ${LICENSE_SQL_ACTIVE_CONDITION}
         AND (l.hardware_id IS NULL OR btrim(l.hardware_id) = '')
         AND l.created_at < NOW() - INTERVAL '7 days'
         AND (c.id IS NULL OR c.archived_at IS NULL)
       ORDER BY l.created_at ASC
       LIMIT ${SAMPLE_LIMIT}
    `);

    const sampleStaleCheckin = await pool.query(`
      SELECT l.id, l.license_key, ci.last_check_in_at, c.name AS customer_name
        FROM licenses l
        LEFT JOIN customers c ON c.id = l.customer_id
        LEFT JOIN LATERAL (
          SELECT MAX(created_at) AS last_check_in_at
            FROM check_ins_log
           WHERE license_id = l.id
        ) ci ON TRUE
       WHERE l.archived_at IS NULL
         AND l.hardware_id IS NOT NULL
         AND btrim(l.hardware_id) <> ''
         AND (ci.last_check_in_at IS NULL OR ci.last_check_in_at < NOW() - INTERVAL '7 days')
         AND (c.id IS NULL OR c.archived_at IS NULL)
       ORDER BY ci.last_check_in_at ASC NULLS FIRST
       LIMIT ${SAMPLE_LIMIT}
    `);

    // 30.15 / GA5.12 — só alerta; rebind autorizado filtrado na avaliação
    const multiAppliance = await collectMultiApplianceAlerts(pool, {
      sampleLimit: SAMPLE_LIMIT,
    });

    let installStats = { total: 0, unlicensed: 0, stale_7d: 0 };
    try {
      const installRows = await pool.query(`
      SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE last_seen_at < NOW() - INTERVAL '7 days') AS stale_7d,
        COUNT(*) FILTER (
          WHERE NOT EXISTS (
            SELECT 1
              FROM licenses l
             WHERE l.archived_at IS NULL
               AND lower(btrim(COALESCE(l.hardware_id, ''))) = i.hardware_id
          )
        ) AS unlicensed
      FROM install_instances i
    `);
      installStats = {
        total: parseInt(installRows.rows[0].total, 10),
        unlicensed: parseInt(installRows.rows[0].unlicensed, 10),
        stale_7d: parseInt(installRows.rows[0].stale_7d, 10),
      };
    } catch (installErr) {
      console.error('[DASHBOARD] Installations stats skipped:', installErr.message);
    }

    res.json({
      licenses: {
        active: parseInt(stats.rows[0].active, 10),
        expired: parseInt(stats.rows[0].expired, 10),
        revoked: parseInt(stats.rows[0].revoked, 10),
        total: parseInt(stats.rows[0].total, 10),
        expiring_30d: parseInt(expiring30d.rows[0].total, 10),
        expired_bound: parseInt(expiredBound.rows[0].total, 10),
        unbound_stale_7d: parseInt(unboundStale.rows[0].total, 10),
        stale_checkin_7d: parseInt(staleCheckin.rows[0].total, 10),
        multi_appliance_abuse: multiAppliance.total,
      },
      installations: {
        total: installStats.total,
        unlicensed: installStats.unlicensed,
        stale_7d: installStats.stale_7d,
      },
      customers: parseInt(customers.rows[0].total, 10),
      activations_24h: parseInt(activations24h.rows[0].count, 10),
      recent_activations: recentActivations.rows,
      action_queue: {
        expiring_30d: sampleExpiring.rows,
        expired_bound: sampleExpiredBound.rows,
        unbound_stale_7d: sampleUnbound.rows,
        stale_checkin_7d: sampleStaleCheckin.rows,
        multi_appliance_abuse: multiAppliance.sample,
      },
      multi_appliance_abuse: {
        lookback_days: multiAppliance.lookback_days,
        total: multiAppliance.total,
        policy: 'alert_only',
      },
    });
  } catch (err) {
    console.error('[DASHBOARD] Error:', err.message);
    res.status(500).json({ error: 'Erro ao carregar dashboard' });
  }
});

module.exports = router;
