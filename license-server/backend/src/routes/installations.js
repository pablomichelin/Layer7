const { Router } = require('express');
const pool = require('../db');
const auth = require('../auth');
const { createHttpError, isHttpError } = require('../crud-integrity');
const { parseIdParam } = require('../crud-validation');
const { requirePermission } = require('../require-permission');
const { parseInstallationsListQuery } = require('../install-ping-parse');
const { ADMIN_INTERNAL_ERROR_MESSAGE } = require('../admin-surface');

const router = Router();
router.use(auth);
router.use(requirePermission('licenses.read'));

const LICENSE_JOIN = `
LEFT JOIN LATERAL (
  SELECT l.id AS license_id,
         l.license_key,
         l.status AS license_status,
         c.id AS customer_id,
         c.name AS customer_name
    FROM licenses l
    LEFT JOIN customers c ON c.id = l.customer_id AND c.archived_at IS NULL
   WHERE l.archived_at IS NULL
     AND lower(btrim(COALESCE(l.hardware_id, ''))) = i.hardware_id
   ORDER BY l.id DESC
   LIMIT 1
) lic ON TRUE
`;

function buildFilters(parsed) {
  const where = [];
  const params = [];

  if (parsed.hardwareId) {
    params.push(parsed.hardwareId);
    where.push(`i.hardware_id = $${params.length}`);
  }

  if (parsed.licensed === true) {
    where.push('lic.license_id IS NOT NULL');
  } else if (parsed.licensed === false) {
    where.push('lic.license_id IS NULL');
  }

  if (parsed.staleDays) {
    params.push(parsed.staleDays);
    where.push(`i.last_seen_at < NOW() - ($${params.length}::int * INTERVAL '1 day')`);
  }

  if (parsed.search) {
    params.push(`%${parsed.search}%`);
    const idx = `$${params.length}`;
    where.push(`(
      COALESCE(i.fqdn, '') ILIKE ${idx}
      OR COALESCE(i.hostname, '') ILIKE ${idx}
      OR COALESCE(i.domain, '') ILIKE ${idx}
      OR COALESCE(i.wan_ipv4, '') ILIKE ${idx}
      OR COALESCE(i.wan_ipv6, '') ILIKE ${idx}
      OR COALESCE(i.egress_ip, '') ILIKE ${idx}
      OR COALESCE(i.uniqueid, '') ILIKE ${idx}
      OR COALESCE(i.system_serial, '') ILIKE ${idx}
      OR COALESCE(i.package_version, '') ILIKE ${idx}
      OR COALESCE(i.inventory::text, '') ILIKE ${idx}
    )`);
  }

  return {
    whereSql: where.length ? `WHERE ${where.join(' AND ')}` : '',
    params,
  };
}

router.get('/', async (req, res) => {
  try {
    const parsed = parseInstallationsListQuery(req.query);
    const { whereSql, params } = buildFilters(parsed);

    const countResult = await pool.query(
      `SELECT COUNT(*) AS total
         FROM install_instances i
         ${LICENSE_JOIN}
         ${whereSql}`,
      params
    );

    const listParams = [...params, parsed.limit, parsed.offset];
    const limitIdx = params.length + 1;
    const offsetIdx = params.length + 2;
    const listResult = await pool.query(
      `SELECT i.*,
              lic.license_id,
              lic.license_key,
              lic.license_status,
              lic.customer_id,
              lic.customer_name
         FROM install_instances i
         ${LICENSE_JOIN}
         ${whereSql}
        ORDER BY i.last_seen_at DESC NULLS LAST, i.id DESC
        LIMIT $${limitIdx} OFFSET $${offsetIdx}`,
      listParams
    );

    const total = parseInt(countResult.rows[0].total, 10);
    return res.json({
      installations: listResult.rows,
      total,
      page: parsed.page,
      pages: Math.max(1, Math.ceil(total / parsed.limit)),
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }
    console.error('[INSTALLATIONS] List error:', error.message);
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

router.get('/:id', async (req, res) => {
  try {
    const id = parseIdParam(req.params.id, 'installation_id');
    const instanceResult = await pool.query(
      `SELECT i.*,
              lic.license_id,
              lic.license_key,
              lic.license_status,
              lic.customer_id,
              lic.customer_name
         FROM install_instances i
         ${LICENSE_JOIN}
        WHERE i.id = $1`,
      [id]
    );

    if (instanceResult.rows.length === 0) {
      throw createHttpError(404, 'Instalacao nao encontrada.');
    }

    const pingsResult = await pool.query(
      `SELECT *
         FROM install_pings_log
        WHERE instance_id = $1
        ORDER BY created_at DESC
        LIMIT 50`,
      [id]
    );

    return res.json({
      installation: instanceResult.rows[0],
      pings: pingsResult.rows,
    });
  } catch (error) {
    if (isHttpError(error)) {
      return res.status(error.status).json({ error: error.message });
    }
    console.error('[INSTALLATIONS] Detail error:', error.message);
    return res.status(500).json({ error: ADMIN_INTERNAL_ERROR_MESSAGE });
  }
});

module.exports = router;
