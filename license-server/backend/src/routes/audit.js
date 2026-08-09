const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');
const { isHttpError } = require('../crud-integrity');
const { parseAuditListQuery } = require('../crud-validation');
const { requirePermission } = require('../require-permission');

const router = Router();
router.use(auth);
router.use(requirePermission('audit.read'));

router.get('/', async (req, res) => {
  try {
    const {
      page,
      limit,
      offset,
      eventType,
      result,
      search,
      customerId,
      licenseId,
    } = parseAuditListQuery(req.query);

    const conditions = [];
    const params = [];

    if (eventType) {
      params.push(eventType);
      conditions.push(`event_type = $${params.length}`);
    }

    if (result) {
      params.push(result);
      conditions.push(`result = $${params.length}`);
    }

    if (customerId) {
      params.push(customerId);
      conditions.push(`(metadata ->> 'customer_id')::int = $${params.length}`);
    }

    if (licenseId) {
      params.push(licenseId);
      conditions.push(`(metadata ->> 'license_id')::int = $${params.length}`);
    }

    if (search) {
      params.push(`%${search}%`);
      conditions.push(
        `(actor_identifier ILIKE $${params.length}
          OR COALESCE(reason, '') ILIKE $${params.length}
          OR COALESCE(route, '') ILIKE $${params.length}
          OR COALESCE(ip_address, '') ILIKE $${params.length}
          OR COALESCE(component, '') ILIKE $${params.length})`
      );
    }

    const whereClause = conditions.length > 0 ? `WHERE ${conditions.join(' AND ')}` : '';

    const countResult = await pool.query(
      `SELECT COUNT(*) FROM admin_audit_log ${whereClause}`,
      params
    );
    const total = Number.parseInt(countResult.rows[0].count, 10);

    const rowsResult = await pool.query(
      `SELECT id, component, event_type, actor_admin_id, actor_identifier,
              ip_address, user_agent, route, result, reason, metadata, created_at
         FROM admin_audit_log
         ${whereClause}
        ORDER BY created_at DESC
        LIMIT $${params.length + 1} OFFSET $${params.length + 2}`,
      [...params, limit, offset]
    );

    return res.json({
      events: rowsResult.rows,
      total,
      page,
      limit,
      pages: Math.max(1, Math.ceil(total / limit)),
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }

    console.error('[AUDIT] List error:', error.message);
    return res.status(500).json({ error: 'Erro ao listar auditoria.' });
  }
});

module.exports = router;
